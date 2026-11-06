<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Checklist;

class ChecklistsService
{
  /**
   * One row per (asset, checklist) with due / done / overdue item counts.
   */
  public function getDueAssetRows()
  {
    $sub = (new AssetsService())->getDueAssetsQuery();

    return DB::table(DB::raw("({$sub->toSql()}) as sub"))
      ->mergeBindings($sub->getQuery())
      ->select(
        'sub.id',
        'sub.code',
        'sub.location_name',
        'sub.checklist_id',
        'sub.due_items',
        'sub.done_items',
        'sub.overdue_items'
      )
      ->get();
  }

  public function getAllChecklistsWithDueAssets($assetRows = null)
  {
    $assetRows ??= $this->getDueAssetRows();

    $checklists = Checklist::select('id', 'name', 'instruction')
      ->get()
      ->keyBy(fn($item) => (string) $item->id);

    $rowsByChecklist = $assetRows->groupBy('checklist_id');

    $checklists->transform(function ($checklist) use ($rowsByChecklist) {
      $rows = $rowsByChecklist->get($checklist->id, collect());

      $checklist->total_assets_with_due  = $rows->filter(fn($r) => $r->due_items > 0)->pluck('id')->unique()->count();
      $checklist->total_assets_with_done = $rows->filter(fn($r) => $r->done_items > 0)->pluck('id')->unique()->count();
      $checklist->total_assets_count     = $rows->pluck('id')->unique()->count();

      return $checklist;
    });

    return [
      'checklistArray' => $checklists->values(),
      'checklistMap'   => $checklists,
    ];
  }
}
