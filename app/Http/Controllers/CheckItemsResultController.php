<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\CheckItem;
use App\Models\ChecklistInstance;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\OverdueCalculatorService;

class CheckItemsResultController extends Controller
{
  public function recordResult(Request $request)
  {
    $validated = $request->validate([
      'asset_id'                    => 'required|integer|exists:assets,id',
      'checklist_id'                => 'required|integer|exists:checklists,id',
      'notes'                       => 'nullable|string|max:1000',
      'items'                       => 'required|array|min:1',
      'items.*.checklist_item_id'   => 'required|integer|exists:checklist_items,id',
      'items.*.item_status'         => 'nullable|string',
      'items.*.remarks'             => 'nullable|string|max:500',
      'items.*.period_start'        => 'nullable|date',
      'items.*.period_end'          => 'nullable|date',
    ]);

    Log::info("validated: " . json_encode($validated));

    $assetId     = $validated['asset_id'];
    $checklistId = $validated['checklist_id'];
    $checkedBy   = session('emp_data')['emp_id'] ?? null;
    $checkedAt = Carbon::now('Asia/Manila');

    $filteredItems = array_filter(
      $validated['items'],
      fn($item) => isset($item['item_status']) && trim($item['item_status']) !== ''
    );

    $itemIds = array_column($filteredItems, 'checklist_item_id');

    $schedulesByItemId = ChecklistItem::with('entitySchedule.schedule')
      ->whereIn('id', $itemIds)
      ->get()
      ->keyBy('id')
      ->map(fn($checklistItem) => $checklistItem->entitySchedule->schedule);

    DB::listen(fn($q) => Log::info($q->sql . ' -- ' . json_encode($q->bindings)));

    $derivedItems = array_map(function ($item) use (
      $assetId,
      $checkedBy,
      $checkedAt,
      $schedulesByItemId
    ) {
      $schedule          = $schedulesByItemId[$item['checklist_item_id']];
      [$start, $end]     = OverdueCalculatorService::derivePeriod($checkedAt, $schedule->toArray());

      Log::info("period end " . json_encode(isset($item['period_end'])
        ? Carbon::parse($item['period_end'])
        : $end->copy()->utc()));

      Log::info("checkedAt " . json_encode($checkedAt));

      return [
        'asset_id'              => $assetId,
        'checked_by'            => $checkedBy,
        'checklist_item_id'     => $item['checklist_item_id'],
        'item_status'           => $item['item_status'],
        'remarks'               => $item['remarks'] ?? null,
        'period_start' => isset($item['period_start'])
          ? Carbon::parse($item['period_start'])
          : $start->copy()->utc(),
        'period_end' => isset($item['period_end'])
          ? Carbon::parse($item['period_end'])
          : $end->copy()->utc(),
      ];
    }, $filteredItems);

    $isLate = collect($derivedItems)->some(
      fn($item) => $checkedAt->gt(Carbon::parse($item['period_end']))
    );

    $checklistInstance = ChecklistInstance::create([
      'checklist_id' => $checklistId,
      'created_by'   => $checkedBy,
      'notes'        => $validated['notes'] ?? null,
      'submission_type' => $isLate ? 'late' : 'punctual',
    ]);

    if ($isLate) {
      $config = DB::table('checklist_approval_config')
        ->where(function ($q) {
          $q->whereNull('instance_type')
            ->orWhere('instance_type', 'checklist');
        })
        ->get();

      $approverRows = $config->map(fn($c) => [
        'instance_id'   => $checklistInstance->id,
        'instance_type' => 'checklist',
        'user_id'       => $c->user_id,
        'level'         => $c->level,
        'status'        => 'pending',
      ])->toArray();

      DB::table('checklist_approvers')->insert($approverRows);
    }

    $insertData = array_map(
      fn($item) => array_merge($item, ['checklist_instance_id' => $checklistInstance->id]),
      $derivedItems
    );

    ChecklistItemResult::insert(array_values($insertData));

    return response()->json(['status' => 'ok']);
  }

  public function index(Request $request)
  {
    $search = $request->input('search', '');
    $perPage = $request->input('perPage', 100);
    $totalEntries = CheckItem::count();

    $checkItems = CheckItem::query()
      ->when($search, function ($query, $search) {
        $query->where(function ($q) use ($search) {
          $q->orWhere('name', 'like', "%{$search}%");
        });
      })
      ->orderBy('name')
      ->paginate($perPage)
      ->withQueryString();

    if ($request->wantsJson()) {
      return response()->json([
        'checkItems' => $checkItems,
        'search' => $search,
        'perPage' => $perPage,
        'totalEntries' => $totalEntries,
      ]);
    }

    return Inertia::render('CheckItemList', [
      'checkItems' => $checkItems,
      'search' => $search,
      'perPage' => $perPage,
      'totalEntries' => $totalEntries,
    ]);
  }


  public function getAllCheckItems(Request $request)
  {
    $checklistID = $request->input('checklist_id');

    return CheckItem::where('checklist_id', $checklistID)->get();
  }

  private function validateEntry(Request $request, $id = null)
  {
    return $request->validate(
      [
        'name'      => 'required|string|max:255',
        'description' => 'nullable|string',
      ],
    );
  }

  public function bulkUpdate(Request $request)
  {
    $rows = $request->all();
    $user = session('emp_data');
    Log::info("rows: " . json_encode($rows));

    DB::transaction(function () use ($rows, $user) {

      foreach ($rows as $id => $fields) {

        if (empty($fields)) {
          continue;
        }

        $model = CheckItem::find($id);

        if (!$model) {
          continue;
        }

        $updateData = [];

        foreach ($fields as $column => $value) {
          $updateData[$column] = $value;
        }

        $updateData['modified_by'] = $user['emp_id'] ?? null;
        Log::info("UPDATE DATA: " . json_encode($updateData));

        if (!empty($updateData)) {
          $model->update($updateData);
        }
      }
    });

    return response()->json(['status' => 'ok']);
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $entry = CheckItem::create([
      ...$validated,
      'modified_by' => $user_id,

    ]);

    return response()->json([
      'message' => 'Check Item created successfully',
      'data'    => $entry,
    ], 201);
  }

  public function upsert($id = null)
  {
    $item = $id ? CheckItem::findOrFail($id) : null;

    return Inertia::render('CheckItemUpsert', [
      'toBeEdit' => $item,
    ]);
  }

  public function update(Request $request, $id)
  {
    $item = CheckItem::findOrFail($id);

    $validated = $this->validateEntry($request, $id);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $item->update([
      ...$validated,
      'modified_by' => $user_id,

    ]);

    return response()->json([
      'message' => 'Check Item updated successfully',
      'data'    => $item,
    ]);
  }

  public function destroy($id)
  {
    try {
      $item = CheckItem::findOrFail($id);
      $item->delete();

      return response()->json([
        'success' => true,
        'message' => 'Check Item deleted successfully',
      ]);
    } catch (ModelNotFoundException $e) {
      return response()->json([
        'status' => 'error',
        'message' => 'Check Item not found. Please verify the ID.',
      ], 404);
    }
  }
}
