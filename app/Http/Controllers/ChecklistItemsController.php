<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\ChecklistItem;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChecklistItemsController extends Controller
{
  public function index(Request $request)
  {
    $checklistItems = ChecklistItem::query()
      ->with('checklist')
      ->get();

    if ($request->wantsJson()) {
      return response()->json([
        'checklistItems' => $checklistItems,
      ]);
    }

    return Inertia::render('ChecklistItemsList', [
      'checklistItems' => $checklistItems,
    ]);
  }

  public function getAllCheckItems(Request $request)
  {
    $checklistID = $request->input('checklist_id');

    return ChecklistItem::where('checklist_id', $checklistID)
      // ->select('criteria')
      // ->with(['item', 'schedule:id,schedule_name'])
      ->with(['item', 'schedule'])
      ->get();
  }

  private function validateEntry(Request $request, $id = null)
  {
    return $request->validate(
      [
        'checklist_id' => 'required|integer|exists:checklists,id',
        'item_id'      => 'required|integer',
        'criteria'     => [
          'required',
          'string',
          'max:255',
          Rule::unique('checklist_items')
            ->where(
              fn($q) => $q
                ->where('checklist_id', $request->checklist_id)
                ->where('item_id', $request->item_id)
            )
            ->ignore($id),
        ]
      ],
      [
        'criteria.unique' =>
        'This combination of checklist, item, and criteria already exists.',
        'checklist_id.exists' =>
        'The selected checklist was not found. Please double-check and try again.',
      ],
    );
  }

  public function massGenocide(Request $request)
  {
    $ids = $request->input('ids'); // expect array

    if (!is_array($ids) || empty($ids)) {
      return response()->json([
        'status' => 'error',
        'message' => 'No IDs provided.',
      ], 422);
    }

    $deleted = ChecklistItem::whereIn('id', $ids)->delete();

    return response()->json([
      'success' => true,
      'deleted_count' => $deleted,
    ]);
  }

  public function bulkUpdate(Request $request)
  {
    $rows = $request->all();
    $user = session('emp_data');
    Log::info("rowaaaaaas: " . json_encode($rows));

    $updateData = [];
    $insertData = [];

    foreach ($rows as $key => $entry) {
      $row = [];


      $row['item_id'] = $entry['item']['id'] ?? null;
      $row['schedule_id'] = $entry['schedule']['id'] ?? null;

      // Extract other columns if needed
      $row['criteria'] = $entry['criteria'] ?? null;
      $row['checklist_id'] = $entry['checklist_id'] ?? null;

      if (is_numeric($key)) {
        $row['id'] = $key;
        $updateData[] = $row;
      } else {
        $insertData[] = $row;
      }
    }

    $duplicateRows = [];
    // test case this
    foreach ($insertData as $index => $row) {
      $exists = ChecklistItem::where('checklist_id', $row['checklist_id'])
        ->where('item_id', $row['item_id'])
        ->where('criteria', $row['criteria'])
        ->exists();

      if ($exists) {
        $duplicateRows[] = [
          'index' => $index,
          'item_id' => $row['item_id'],
          'criteria' => $row['criteria'],
        ];
      }
    }

    if (!empty($duplicateRows)) {
      return response()->json([
        'message' => 'Some checklist items already exist with the same item and criteria.',
        'duplicates' => $duplicateRows,
      ], 422);
    }

    $updateDataForChecklistItem = array_map(fn($row) => array_diff_key($row, ['schedule_id' => '']), $updateData);

    Log::info("updateData: " . json_encode($updateData));
    Log::info("insertData " . json_encode($insertData));
    Log::info("updateDataForChecklistItem " . json_encode($updateDataForChecklistItem));

    DB::transaction(function () use ($updateDataForChecklistItem, $insertData, $updateData, $rows, $user) {
      ChecklistItem::upsert(
        array_map(fn($row) => array_merge($row, [
          'modified_by' => $user['emp_id'] ?? null,
          'modified_at' => Carbon::now()
        ]), $updateDataForChecklistItem),
        ['id'],
        ['item_id', 'criteria', 'checklist_id', 'modified_by', 'modified_at']
      );

      foreach ($updateData as $row) {
        Log::info("row: " . json_encode($row));
        $scheduleId = $row['schedule_id'] ?? null;
        $id = $row['id'];
        if ($scheduleId === null) {
          // Remove existing schedule if any
          DB::table('entity_checklist_item_schedules')
            ->where('checklist_item_id', $id)
            ->delete();
        } else {
          // Update or insert schedule
          DB::table('entity_checklist_item_schedules')
            ->updateOrInsert(
              ['checklist_item_id' => $id],
              ['schedule_id' => $scheduleId]
            );
        }
      }

      foreach ($insertData as $row) {
        $scheduleId = $row['schedule_id'] ?? null;
        unset($row['schedule_id']);

        Log::info("row: " . $user['emp_id']);
        $item = ChecklistItem::create(array_merge($row, [
          'modified_by' => $user['emp_id'] ?? null,
          'modified_at' => Carbon::now(),
        ]));

        if ($scheduleId !== null) {
          DB::table('entity_checklist_item_schedules')->insert([
            'checklist_item_id' => $item->id,
            'schedule_id' => $scheduleId,
          ]);
        }
      }
    });

    return response()->json(['status' => 'ok']);
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $entry = ChecklistItem::create([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Checklist item created successfully',
      'data'    => $entry,
    ], 201);
  }

  public function upsert($id = null)
  {
    $item = $id ? ChecklistItem::findOrFail($id) : null;

    return Inertia::render('ChecklistItemUpsert', [
      'toBeEdit' => $item,
    ]);
  }

  public function update(Request $request, $id)
  {
    $item = ChecklistItem::findOrFail($id);

    $validated = $this->validateEntry($request, $id);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $item->update([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Checklist item updated successfully',
      'data'    => $item,
    ]);
  }

  public function destroy($id)
  {
    try {
      $item = ChecklistItem::findOrFail($id);
      $item->delete();

      return response()->json([
        'success' => true,
        'message' => 'Checklist item deleted successfully',
      ]);
    } catch (ModelNotFoundException $e) {
      return response()->json([
        'status' => 'error',
        'message' => 'Checklist item not found. Please verify the ID.',
      ], 404);
    }
  }
}
