<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\Checklist;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Support\CacheKeys;
use App\Services\AssetsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Repositories\ChecklistsRepository;
use App\Services\BulkUpserter;

class ChecklistsController extends Controller
{
  public function viewChecklistItems(Request $request)
  {
    $search = $request->input('search', '');
    $perPage = $request->input('perPage', 100);
    $totalEntries = Checklist::count();

    $checklists = (new ChecklistsRepository())->getChecklistsQuery($search)
      ->paginate($perPage)
      ->withQueryString();

    if ($request->wantsJson()) {
      return response()->json([
        'checklist' => $checklists,
        'search' => $search,
        'perPage' => $perPage,
        'totalEntries' => $totalEntries,
      ]);
    }

    return Inertia::render('ChecklistItemList', [
      'checklist' => $checklists,
      'search' => $search,
      'perPage' => $perPage,
      'totalEntries' => $totalEntries,
    ]);
  }

  public function index(Request $request)
  {
    $search = $request->input('search', '');
    $perPage = $request->input('perPage', 100);
    $totalEntries = Checklist::count();

    $checklists = (new ChecklistsRepository())->getChecklistsQuery($search)
      ->paginate($perPage)
      ->withQueryString();

    if ($request->wantsJson()) {
      return response()->json([
        'checklist' => $checklists,
        'search' => $search,
        'perPage' => $perPage,
        'totalEntries' => $totalEntries,
      ]);
    }

    return Inertia::render('ChecklistList', [
      'checklist' => $checklists,
      'search' => $search,
      'perPage' => $perPage,
      'totalEntries' => $totalEntries,
    ]);
  }

  private function validateEntry(Request $request, $id = null)
  {
    return $request->validate(
      [
        'name' => [
          'required',
          'string',
          'max:255',
          Rule::unique('checklists')->where(function ($query) use ($request) {
            return $query->where('name', $request->name);
          })->ignore($id),
        ],
        'description'      => 'nullable|string',
      ],
      [
        'name.unique' =>
        'The name provided already exists.',
      ],
    );
  }

  public function bulkUpdate(Request $request)
  {
    $rows = $request->all();
    $user = session('emp_data');

    $columnRules = [
      'name' => fn($id) => [
        'required',
        'string',
        Rule::unique('checklists', 'name')
          ->ignore(is_numeric($id) ? $id : null),
      ],
      'description' => 'nullable',
      'instruction' => 'nullable',
    ];

    $rows = array_map(function ($row) use ($user) {
      $row['modified_by'] = $user['emp_id'] ?? null;
      return $row;
    }, $rows);

    $bulkUpdater = new BulkUpserter(new Checklist(), $columnRules, [], []);

    $result = $bulkUpdater->update($rows ?? null);

    if (!empty($result['errors'])) {
      return response()->json([
        'status' => 'error',
        'message' => 'You have ' . count($result['errors']) . ' error/s',
        'data' => $result['errors']
      ], 422);
    }

    return response()->json([
      'status' => 'ok',
      'message' => 'Updated successfully',
      'updated' => $result['updated']
    ]);
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $entry = Checklist::create([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Item created successfully',
      'data'    => $entry,
    ], 201);
  }

  public function upsert($id = null)
  {
    $item = $id ? Checklist::findOrFail($id) : null;

    return Inertia::render('ChecklistUpsert', [
      'toBeEdit' => $item,
    ]);
  }

  public function update(Request $request, $id)
  {
    $item = Checklist::findOrFail($id);

    $validated = $this->validateEntry($request, $id);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $item->update([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Item updated successfully',
      'data'    => $item,
    ]);
  }

  public function destroy($id)
  {
    try {
      $item = Checklist::findOrFail($id);
      $item->delete();

      return response()->json([
        'success' => true,
        'message' => 'Item deleted successfully',
      ]);
    } catch (ModelNotFoundException $e) {
      return response()->json([
        'status' => 'error',
        'message' => 'Item not found. Please verify the ID.',
      ], 404);
    }
  }

  public function getAllChecklistsWithDueAssets(Request $request)
  {
    $assetsService = new AssetsService();

    return Cache::remember(CacheKeys::checklistsAll(), CacheKeys::defaultCacheDuration(), function () use ($assetsService) {
      $checklists = Checklist::select('id', 'name', 'instruction')
        ->get()
        ->keyBy(fn($item) => (string)$item->id);

      $assetsQuery = $assetsService->getDueAssetsQuery();

      // Log::info("query get" . json_encode($assetsQuery->get()));

      $assetStats = DB::table(DB::raw("({$assetsQuery->toSql()}) as sub"))
        ->mergeBindings($assetsQuery->getQuery())
        ->select(
          'sub.checklist_id',
          DB::raw('COUNT(DISTINCT sub.id) AS total_assets_count'),
          DB::raw('COUNT(DISTINCT CASE WHEN sub.due_items > 0 THEN sub.id END) AS assets_with_due'),
          DB::raw('COUNT(DISTINCT CASE WHEN sub.done_items > 0 THEN sub.id END) AS assets_with_done')
        )
        ->groupBy('sub.checklist_id')
        ->get()
        ->keyBy('checklist_id');

      $checklists->transform(function ($checklist) use ($assetStats) {
        $stats = $assetStats->get($checklist->id);

        $checklist->total_assets_with_due  = $stats->assets_with_due ?? 0;
        $checklist->total_assets_with_done = $stats->assets_with_done ?? 0;
        $checklist->total_assets_count     = $stats->total_assets_count ?? 0;

        return $checklist;
      });

      return response()->json([
        'checklistArray' => $checklists->values(),
        'checklistMap'   => $checklists,
      ]);
    });
  }
}
