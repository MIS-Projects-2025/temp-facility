<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Checklist;
use App\Models\ChecklistItemResult;
use App\Constants\DueScheduleQuery;

class CheckItemsResultRepository
{
  public static function latestRunningHoursByChecklist(int $checklistId)
  {
    $checkItemName = 'Running hours';

    $latestResults = DB::table('checklist_item_results as cir2')
      ->select('cir2.asset_id', DB::raw('MAX(cir2.checked_at) as latest_checked'))
      ->join('checklist_items as ci2', 'ci2.id', '=', 'cir2.checklist_item_id')
      ->join('check_items as ct2', 'ct2.id', '=', 'ci2.item_id')
      ->where('ci2.checklist_id', $checklistId)
      ->where('ct2.name', $checkItemName)
      ->groupBy('cir2.asset_id');

    return ChecklistItemResult::query()
      ->with('checkedBy:EMPLOYID,FIRSTNAME,LASTNAME,JOB_TITLE')
      ->from('checklist_item_results as cir')
      ->select(
        [
          'cir.checked_at',
          'cir.item_status',
          'a.code as asset_name',
          'l.location_name as asset_location',
          'cir.checked_by',
          DB::raw("s.id IS NULL as is_no_schedule"),
        ]
      )
      ->addSelect(DueScheduleQuery::dueRaw())
      ->join('checklist_items as ci', 'ci.id', '=', 'cir.checklist_item_id')
      ->leftjoin('entity_checklist_item_schedules as ecs', 'ecs.checklist_item_id', '=', 'ci.id')
      ->leftjoin('schedules as s', 's.id', '=', 'ecs.schedule_id')
      ->join('check_items as ct', 'ct.id', '=', 'ci.item_id')
      ->join('assets as a', 'a.id', '=', 'cir.asset_id')
      ->join('locations as l', 'l.id', '=', 'a.location_id')
      ->joinSub($latestResults, 'latest', function ($join) {
        $join->on('latest.asset_id', '=', 'cir.asset_id')
          ->on('latest.latest_checked', '=', 'cir.checked_at');
      })
      ->where('ci.checklist_id', $checklistId)
      ->where('ct.name', $checkItemName)
      ->get();
  }
}
