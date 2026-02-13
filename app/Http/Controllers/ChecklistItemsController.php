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
use Illuminate\Database\QueryException;
use App\Traits\MassDeletesByIds;
use App\Constants\DueScheduleQuery;

class ChecklistItemsController extends Controller
{
  use MassDeletesByIds;

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
    Log::info("Fjlaksjdfklsdajflsdjlsjfljdflsdjfldjfldj");

    return $this->massDeleteByIds(
      $request,
      ChecklistItem::class
    );
  }

  public function getScheduledCheckItems(Request $request)
  {
    $assetId = $request->input('assetId');
    $checklistId = $request->input('checklistId');

    $query = DB::table('checklist_items as ci')
      ->select([
        'ci.id',
        'i.name',
        'ci.item_id',
        'cir.verified_by',
        'ci.criteria',
        's.schedule_name',
        'cir.checked_at',
        DB::raw("
                CASE
                    -- Daily interval
                    WHEN " . DueScheduleQuery::intervalDay . "
                        THEN 1
                    -- Weekly interval
                    WHEN " . DueScheduleQuery::intervalWeek . "
                        THEN 1
                    -- Monthly interval
                    WHEN " . DueScheduleQuery::intervalMonth . "
                        THEN 1
                    -- Hourly interval
                    WHEN " . DueScheduleQuery::intervalHour . "
                        THEN 1
                    -- Daily with specific day_times
                    WHEN " . DueScheduleQuery::dailySchedule . "
                        THEN 1
                    ELSE 0
                END as is_due
            ")
      ])
      ->join('check_items as i', 'ci.item_id', '=', 'i.id')
      ->join('checklist_assets as ca', function ($join) use ($assetId) {
        $join->on('ca.checklist_id', '=', 'ci.checklist_id')
          ->where('ca.asset_id', $assetId);
      })
      ->join('entity_checklist_item_schedules as ecs', 'ecs.checklist_item_id', '=', 'ci.id')
      ->join('schedules as s', 's.id', '=', 'ecs.schedule_id')
      ->leftJoin('checklist_item_results as cir', function ($join) use ($assetId) {
        $join->on('cir.checklist_item_id', '=', 'ci.id')
          ->where('cir.asset_id', $assetId)
          ->whereRaw('cir.checked_at = (
                     SELECT MAX(checked_at)
                     FROM checklist_item_results
                     WHERE checklist_item_id = ci.id
                 )');
      })
      ->where('ci.checklist_id', $checklistId)
      ->orderByDesc('is_due'); // due items appear first

    $results = $query->get();

    return response()->json($results);
  }


  // public function getScheduledCheckItems(Request $request)
  // {
  //   $assetId = $request->input('assetId');
  //   $checklistId = $request->input('checklistId');

  //   $query = DB::table('checklist_items as ci')
  //     ->select([
  //       'ci.id',
  //       'i.name',
  //       'ci.checklist_id',
  //       'ci.item_id',
  //       'ci.criteria',
  //       's.schedule_name',
  //       'cir.checked_at',
  //     ])

  //     // check items
  //     ->join('check_items as i', 'ci.item_id', '=', 'i.id')

  //     // checklist_assets
  //     ->join('checklist_assets as ca', function ($join) use ($assetId) {
  //       $join->on('ca.checklist_id', '=', 'ci.checklist_id')
  //         ->where('ca.asset_id', $assetId);
  //     })

  //     // entity_checklist_item_schedules
  //     ->join('entity_checklist_item_schedules as ecs', 'ecs.checklist_item_id', '=', 'ci.id')

  //     // schedules
  //     ->join('schedules as s', 's.id', '=', 'ecs.schedule_id')

  //     // latest checklist_item_results per item
  //     ->leftJoin('checklist_item_results as cir', function ($join) use ($assetId) {
  //       $join->on('cir.checklist_item_id', '=', 'ci.id')
  //         ->where('cir.asset_id', $assetId)
  //         ->whereRaw('cir.checked_at = (
  //                SELECT MAX(checked_at)
  //                FROM checklist_item_results
  //                WHERE checklist_item_id = ci.id
  //            )');
  //     })

  //     ->where('ci.checklist_id', $checklistId)

  //     ->where(function ($q) {

  //       // Due daily
  //       $q->where(function ($q) {
  //         $q->where('s.recurrence_type', 'interval')
  //           ->where('s.interval_unit', 'day')
  //           ->whereRaw('
  //                 cir.checked_at IS NULL
  //                 OR DATEDIFF(CURDATE(), DATE(cir.checked_at)) >= s.interval_value
  //             ');
  //       })

  //         // Due weekly
  //         ->orWhere(function ($q) {
  //           $q->where('s.recurrence_type', 'interval')
  //             ->where('s.interval_unit', 'week')
  //             ->whereRaw('
  //                 cir.checked_at IS NULL
  //                 OR FLOOR(DATEDIFF(CURDATE(), DATE(cir.checked_at)) / 7) >= s.interval_value
  //             ');
  //         })

  //         // Due monthly
  //         ->orWhere(function ($q) {
  //           $q->where('s.recurrence_type', 'interval')
  //             ->where('s.interval_unit', 'month')
  //             ->whereRaw('
  //                 cir.checked_at IS NULL
  //                 OR PERIOD_DIFF(
  //                     EXTRACT(YEAR_MONTH FROM CURDATE()),
  //                     EXTRACT(YEAR_MONTH FROM DATE(cir.checked_at))
  //                 ) >= s.interval_value
  //             ');
  //         })

  //         // Due hourly
  //         ->orWhere(function ($q) {
  //           $q->where('s.recurrence_type', 'interval')
  //             ->where('s.interval_unit', 'hour')
  //             ->whereRaw('
  //                 cir.checked_at IS NULL
  //                 OR TIMESTAMPDIFF(HOUR, cir.checked_at, NOW()) >= s.interval_value
  //             ');
  //         })

  //         // Daily with day_times JSON
  //         ->orWhere(function ($q) {
  //           $q->where('s.recurrence_type', 'daily')
  //             ->where('s.interval_unit', 'day')
  //             ->whereNotNull('s.day_times')
  //             ->whereExists(function ($sub) {
  //               $sub->select(DB::raw(1))
  //                 ->from(DB::raw("
  //                         JSON_TABLE(
  //                             s.day_times,
  //                             '$[*]' COLUMNS (
  //                                 t TIME PATH '$'
  //                             )
  //                         ) as jt
  //                     "))
  //                 ->whereRaw('
  //                         TIMESTAMP(CURDATE(), jt.t) <= NOW()
  //                         AND (
  //                             cir.checked_at IS NULL
  //                             OR cir.checked_at < TIMESTAMP(CURDATE(), jt.t)
  //                         )
  //                     ');
  //             });
  //         });
  //     });

  //   $results = $query->get();

  //   return response()->json($results);
  // }

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

    try {
      DB::transaction(function () use (
        $updateDataForChecklistItem,
        $insertData,
        $updateData,
        $rows,
        $user
      ) {

        ChecklistItem::upsert(
          array_map(fn($row) => array_merge($row, [
            'modified_by' => $user['emp_id'] ?? null,
            'modified_at' => Carbon::now(),
          ]), $updateDataForChecklistItem),
          ['id'],
          ['item_id', 'criteria', 'checklist_id', 'modified_by', 'modified_at']
        );

        foreach ($updateData as $row) {
          $scheduleId = $row['schedule_id'] ?? null;
          $id = $row['id'];

          if ($scheduleId === null) {
            DB::table('entity_checklist_item_schedules')
              ->where('checklist_item_id', $id)
              ->delete();
          } else {
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
    } catch (QueryException $e) {

      // MySQL duplicate key error
      if ($e->errorInfo[1] === 1062) {
        return response()->json([
          'message' => 'A checklist item with the same item and criteria already exists.'
        ], 422);
      }

      throw $e; // anything else is a real failure
    }

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
