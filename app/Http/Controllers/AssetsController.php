<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Asset;
use App\Models\ChecklistAssets;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use App\Traits\MassDeletesByIds;

class AssetsController extends Controller
{
  use MassDeletesByIds;

  public function index(Request $request)
  {
    $search = $request->input('search', '');
    $perPage = $request->input('perPage', 30);
    $checklistId = $request->input('checklistId', null); // optional filter
    $totalEntries = Asset::count();

    $assetsQuery = Asset::query()
      ->with(['location'])
      // $assetsQuery = Asset::query()
      //   // optional checklist filter
      //   ->when($checklistId, function ($query) use ($checklistId) {
      //     $query->whereExists(function ($subQuery) use ($checklistId) {
      //       $subQuery->select(DB::raw(1))
      //         ->from('checklist_assets as ac')
      //         ->whereColumn('ac.asset_id', 'assets.id')
      //         ->where('ac.checklist_id', $checklistId);
      //     });
      //   })

      ->when($search !== '', function ($query) use ($search) {
        $query->where(function ($q) use ($search) {
          $q->where('code', 'like', "%{$search}%");
        });
      })
      ->orderBy('code', 'asc');

    // paginated result
    $assets = $assetsQuery->paginate($perPage);

    if ($request->wantsJson()) {
      return response()->json([
        'assets' => $assets,
        'search' => $search,
        'perPage' => $perPage,
        'checklistId' => $checklistId,
        'totalEntries' => $totalEntries,
      ]);
    }

    Log::info('assets: ', [$assets]);
    return Inertia::render('AssetList', [
      'assets' => $assets,
      'search' => $search,
      'perPage' => $perPage,
      'checklistId' => $checklistId,
      'totalEntries' => $totalEntries,
    ]);
  }

  public function getAllAssets(Request $request)
  {
    $checklistID = $request->input('checklist_id');

    return Asset::where('checklist_id', $checklistID)
      ->with(['location'])
      ->get();
  }

  private function validateEntry(Request $request, $id = null)
  {
    return $request->validate(
      [
        'location_id' => 'nullable|integer|exists:locations,id',
        'code'      => [
          'required',
          'string',
          'max:120',
          Rule::unique('assets')->where(function ($query) use ($request) {
            return $query->where('code', $request->code);
          })->ignore($id),
        ],
        'properties' => 'nullable|array',
      ],
      [
        'checklist_id.exists' =>
        'The selected checklist was not found. Please double-check and try again.',
        'code.unique' =>
        'The code provided already exists.',
      ],
    );
  }

  private function assetRules($id = null)
  {
    return [
      'location_id' => 'nullable|integer|exists:locations,id',
      'code' => [
        'required',
        'string',
        'max:120',
        Rule::unique('assets', 'code')->ignore($id),
      ],
      'properties' => 'nullable|array',
    ];
  }

  private function assetMessages()
  {
    return [
      'location_id.exists' =>
      'The selected location was not found. Please double-check and try again.',
      'code.unique' =>
      'The code provided already exists.',
    ];
  }

  public function massGenocide(Request $request)
  {
    return $this->massDeleteByIds(
      $request,
      Asset::class
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
        'location_id' => $entry['location']['id'] ?? null,
        'code' => $entry['code'] ?? null,
        'properties' => $entry['properties'] ?? null,
      ];

      $id = is_numeric($key) ? $key : null;

      $validator = Validator::make(
        $row,
        $this->assetRules($id),
        $this->assetMessages()
      );
      if ($validator->fails()) {
        return response()->json([
          'status' => 'validation_error',
          'row' => $key,
          'errors' => $validator->errors(),
          'message' => $validator->errors()->first(),
        ], 422);
      }

      $row['properties'] = isset($row['properties']) ? json_encode($row['properties']) : null;

      if ($id) {
        $row['id'] = $id;
        $updateData[] = $row;
      } else {
        $insertData[] = $row;
      }
    }
    Log::info("insertdata: " . json_encode($insertData));
    Log::info(message: "updateData: " . json_encode($updateData));


    try {
      DB::transaction(function () use (
        $insertData,
        $updateData,
        $rows,
        $user
      ) {
        Asset::upsert(
          array_map(fn($row) => array_merge($row, [
            'modified_by' => $user['emp_id'] ?? null,
            'modified_at' => Carbon::now(),
          ]), $updateData),
          ['id'],
          ['code', 'properties', 'location_id', 'modified_by', 'modified_at']
        );

        Asset::insert(
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

    $entry = Asset::create([
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
    $item = $id ? Asset::findOrFail($id) : null;

    return Inertia::render('AssetUpsert', [
      'toBeEdit' => $item,
    ]);
  }

  public function update(Request $request, $id)
  {
    $item = Asset::findOrFail($id);

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
      $item = Asset::findOrFail($id);
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
