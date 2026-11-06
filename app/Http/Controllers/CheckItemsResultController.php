<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\ChecklistInstance;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\OverdueCalculatorService;
use Illuminate\Validation\ValidationException;

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

    $checkedBy = session('emp_data')['emp_id'] ?? null;
    abort_if(!$checkedBy, 401, 'Session expired. Please log in again.');

    abort_unless(
      DB::table('checklist_assets')
        ->where('asset_id', $validated['asset_id'])
        ->where('checklist_id', $validated['checklist_id'])
        ->exists(),
      422,
      'This asset is not assigned to the selected checklist.'
    );

    $assetId     = $validated['asset_id'];
    $checklistId = $validated['checklist_id'];
    $checkedAt   = Carbon::now('Asia/Manila');
    $rows        = collect($validated['items']);

    if ($rows->contains(fn($i) => blank($i['item_status'] ?? null) && filled($i['remarks'] ?? null))) {
      throw ValidationException::withMessages([
        'items' => 'Some items have remarks but no status. Fill in the status or clear the remarks.',
      ]);
    }

    $answered = $rows->filter(fn($i) => filled($i['item_status'] ?? null))->values();
    if ($answered->isEmpty()) {
      throw ValidationException::withMessages(['items' => 'Nothing to submit: no item has a status.']);
    }

    $itemIds = $answered->pluck('checklist_item_id')->unique()->values();
    $checklistItems = ChecklistItem::with('entitySchedule.schedule')
      ->where('checklist_id', $checklistId)
      ->whereIn('id', $itemIds)
      ->get()->keyBy('id');

    if ($checklistItems->count() !== $itemIds->count()) {
      throw ValidationException::withMessages(['items' => 'Some items do not belong to this checklist.']);
    }

    $derived = $answered->map(function ($item) use ($assetId, $checkedBy, $checkedAt, $checklistItems) {
      $schedule = $checklistItems[$item['checklist_item_id']]->entitySchedule?->schedule;
      $start = $end = null;

      if (!empty($item['period_start']) && !empty($item['period_end'])) {
        // overdue/appeal row: period comes from the overdue list
        $start = Carbon::parse($item['period_start'])->utc();
        $end   = Carbon::parse($item['period_end'])->utc();
      } elseif ($schedule) {
        $derivable = true;

        try {
          [$start, $end] = OverdueCalculatorService::derivePeriod($checkedAt, $schedule->toArray());
        } catch (\InvalidArgumentException $e) {
          // hour-based / multi-year schedules cannot derive a period from checked_at
          Log::warning('Cannot derive period for schedule', [
            'schedule_id' => $schedule->id,
            'error'       => $e->getMessage(),
          ]);
          $derivable = false;
          $start = $end = null;
        }

        if ($derivable) {
          if (!$start || !$end) {
            throw ValidationException::withMessages(['items' => 'No schedule window is open for one of the items right now.']);
          }
          $start = $start->copy()->utc();
          $end   = $end->copy()->utc();
        }
      }

      return [
        'asset_id'          => $assetId,
        'checked_by'        => $checkedBy,
        'checklist_item_id' => $item['checklist_item_id'],
        'item_status'       => trim($item['item_status']),
        'remarks'           => $item['remarks'] ?? null,
        'period_start'      => $start,
        'period_end'        => $end,
      ];
    });

    $isLate = $derived->contains(fn($r) => $r['period_end'] && $checkedAt->gt($r['period_end']));

    $instance = DB::transaction(function () use ($checklistId, $checkedBy, $validated, $isLate, $derived) {
      $instance = ChecklistInstance::create([
        'checklist_id'    => $checklistId,
        'created_by'      => $checkedBy,
        'notes'           => $validated['notes'] ?? null,
        'submission_type' => $isLate ? 'late' : 'punctual',
      ]);

      if ($isLate) {
        $approverRows = DB::table('checklist_approval_config')
          ->where(fn($q) => $q->whereNull('instance_type')->orWhere('instance_type', 'checklist'))
          ->get()
          ->map(fn($c) => [
            'instance_id'   => $instance->id,
            'instance_type' => 'checklist',
            'user_id'       => $c->user_id,
            'level'         => $c->level,
            'status'        => 'pending',
          ])->all();

        abort_if(empty($approverRows), 422, 'No approvers are configured for late submissions.');
        DB::table('checklist_approvers')->insert($approverRows);
      }

      ChecklistItemResult::insert(
        $derived->map(fn($r) => $r + ['checklist_instance_id' => $instance->id])->all()
      );

      return $instance;
    });

    return response()->json(['status' => 'ok', 'instance_id' => $instance->id]);
  }
}
