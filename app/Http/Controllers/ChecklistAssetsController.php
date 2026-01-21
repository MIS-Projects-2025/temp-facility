<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ChecklistAssets;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Support\Facades\Validator;
use App\Models\Checklist;
use App\Traits\MassDeletesByIds;

class ChecklistAssetsController extends Controller
{
  use MassDeletesByIds;

  public function index(Request $request)
  {
    $search = $request->input('search', '');
    $perPage = $request->input('perPage', 30);
    $checklistId = $request->input('checklistId', null); // optional filter
    $totalEntries = ChecklistAssets::count();

    if (!$checklistId) {
      $checklistId = Checklist::first()->id;
    }

    $checklistAssetsQuery = ChecklistAssets::query()
      ->with(['asset', 'checklist'])
      // $assetsQuery = ChecklistAssets::query()
      ->when($search, function ($query, $search) {
        $query->whereHas('asset', fn($q) => $q->where('code', 'like', "%{$search}%"));
      })
      //   // optional checklist filter
      ->when($checklistId, function ($query) use ($checklistId) {
        $query->where(function ($q) use ($checklistId) {
          $q->where('checklist_id', $checklistId);
        });
      });

    // paginated result
    $checklistAssets = $checklistAssetsQuery->paginate($perPage);

    if ($request->wantsJson()) {
      return response()->json([
        'assets' => $checklistAssets,
        'search' => $search,
        'perPage' => $perPage,
        'checklistId' => $checklistId,
        'totalEntries' => $totalEntries,
      ]);
    }

    Log::info('assets: ', [$checklistAssets]);
    return Inertia::render('ChecklistAssetList', [
      'assets' => $checklistAssets,
      'search' => $search,
      'perPage' => $perPage,
      'checklistId' => $checklistId,
      'totalEntries' => $totalEntries,
    ]);
  }

  public function getAllAssets(Request $request)
  {
    $checklistID = $request->input('checklist_id');

    return ChecklistAssets::where('checklist_id', $checklistID)
      ->with(['location'])
      ->get();
  }

  private function rules($id, $asset_id)
  {
    return [
      'asset_id' => [
        'required',
        'integer',
        'exists:assets,id',
      ],
      'checklist_id' => [
        'required',
        'integer',
        Rule::unique('checklist_assets')
          ->where(function ($query) use ($asset_id) {
            return $query->where('asset_id', $asset_id);
          })
          ->ignore($id),
      ],
    ];
  }

  private function params()
  {
    return [
      'asset_id.exists' =>
      'The selected checklist was not found. Please double-check and try again.',
      'checklist_id.unique' =>
      'This combination of checklist id and asset id already exists.',
    ];
  }

  private function validateEntry(Request $request, $id = null)
  {
    return $request->validate(
      self::rules($id, $request->asset_id),
      self::params()
    );
  }

  private function assetRules($id = null, $asset_id)
  {
    return self::rules($id, $asset_id);
  }

  public function massGenocide(Request $request)
  {
    return $this->massDeleteByIds(
      $request,
      ChecklistAssets::class
    );
  }

  public function bulkUpdate(Request $request)
  {
    $rows = $request->all();
    $user = session('emp_data');

    $updateData = [];
    $insertData = [];

    foreach ($rows as $key => $entry) {

      $row = [
        'asset_id' => $entry['asset']['id'] ?? null,
        'checklist_id' => $entry['checklist_id'] ?? null,
      ];

      $id = is_numeric($key) ? $key : null;

      $validator = Validator::make(
        $row,
        $this->assetRules($id, $row['asset_id'] ?? null),
        $this->params()
      );

      if ($validator->fails()) {
        return response()->json([
          'status' => 'validation_error',
          'row' => $key,
          'errors' => $validator->errors(),
          'message' => $validator->errors()->first(),
        ], 422);
      }

      if ($id) {
        $row['id'] = $id;
        $updateData[] = $row;
      } else {
        $insertData[] = $row;
      }
    }
    // Log::info("insertdata: ", $insertData);

    try {
      DB::transaction(function () use (
        $insertData,
        $updateData,
        $rows,
        $user
      ) {
        ChecklistAssets::upsert(
          array_map(fn($row) => array_merge($row, [
            'modified_by' => $user['emp_id'] ?? null,
            'modified_at' => Carbon::now(),
          ]), $updateData),
          ['id'],
          ['asset_id', 'checklist_id', 'modified_by', 'modified_at']
        );

        ChecklistAssets::insert(
          array_map(fn($row) => array_merge($row, [
            'modified_by' => $user['emp_id'] ?? null,
            'modified_at' => Carbon::now(),
          ]), $insertData)
        );
      });
    } catch (Exception $e) {
      return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }

    return response()->json(['status' => 'ok']);
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $entry = ChecklistAssets::create([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Asset created successfully',
      'data'    => $entry,
    ], 201);
  }

  public function upsert($id = null)
  {
    $item = $id ? ChecklistAssets::findOrFail($id) : null;

    return Inertia::render('AssetUpsert', [
      'toBeEdit' => $item,
    ]);
  }

  public function update(Request $request, $id)
  {
    $item = ChecklistAssets::findOrFail($id);

    $validated = $this->validateEntry($request, $id);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $item->update([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Asset updated successfully',
      'data'    => $item,
    ]);
  }

  public function destroy($id)
  {
    try {
      $item = ChecklistAssets::findOrFail($id);
      $item->delete();

      return response()->json([
        'success' => true,
        'message' => 'Asset deleted successfully',
      ]);
    } catch (ModelNotFoundException $e) {
      return response()->json([
        'status' => 'error',
        'message' => 'Asset not found. Please verify the ID.',
      ], 404);
    }
  }
}
