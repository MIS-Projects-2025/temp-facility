<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\ChecklistItem;
use App\Models\Employee;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\MassDeletesByIds;
use App\Constants\DueScheduleQuery;
use App\Services\BulkUpserter;
use App\Models\Checklist;
use App\Services\PeriodGeneratorService;

class ChecklistItemsController extends Controller
{
  use MassDeletesByIds;

  public function index(Request $request)
  {
    $allChecklists = Checklist::all();
    $selectedChecklistId = $request->input('checklist_id', $allChecklists->first()?->id);
    $selectedChecklist = Checklist::with([
      'checklistItems.item',
      'checklistItems.schedule',
    ])->find($selectedChecklistId);

    if ($request->wantsJson()) {
      return response()->json([
        'checklists' => $allChecklists,
        'selectedChecklist' => $selectedChecklist
      ]);
    }

    return Inertia::render('ChecklistItemList', [
      'checklists' => $allChecklists,
      'selectedChecklist' => $selectedChecklist
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

    return $this->massDeleteByIds(
      $request,
      ChecklistItem::class
    );
  }

  private function resolveEmployees($results)
  {
    $employeeIds = collect()
      ->merge($results->pluck('created_by'))
      ->merge($results->pluck('verified_by'))
      ->filter()
      ->unique();

    $employees = Employee::whereIn('EMPLOYID', $employeeIds)
      ->select('EMPLOYID', 'FIRSTNAME', 'JOB_TITLE', 'LASTNAME')
      ->get()
      ->keyBy('EMPLOYID');

    return $results->transform(function ($item) use ($employees) {
      $item->created_by  = $employees[$item->created_by] ?? null;
      $item->verified_by = $employees[$item->verified_by] ?? null;
      return $item;
    });
  }

  /**
   * Latest on-time result per checklist item for an asset.
   * Late (appeal) rows are ignored so an appeal for an old period
   * can't silently mark the current period as done.
   */
  private function latestOnTimeResults($assetId)
  {
    return DB::table('checklist_item_results')
      ->select(
        'checklist_item_id',
        'asset_id',
        DB::raw('MAX(checked_at) as checked_at'),
        DB::raw('MAX(id) as id')
      )
      ->where('asset_id', $assetId)
      ->where(fn($q) => $q->whereNull('period_end')->orWhereColumn('checked_at', '<=', 'period_end'))
      ->groupBy('checklist_item_id', 'asset_id');
  }

  public function getOverdueCheckItems(Request $request)
  {
    $assetId = $request->input('assetId');
    $checklistId = $request->input('checklistId');

    $items = ChecklistItem::query()
      ->from('checklist_items as ci')
      ->with('entitySchedule.schedule')
      ->select([
        'ci.id',
        'i.name',
        'ci.item_id',
        'ci.input_type',
        'ci.allowed_values',
        'cs.verified_by',
        'cs.created_by',
        'ci.criteria',
        's.schedule_name',
        's.id as schedule_id',
      ])
      ->join('check_items as i', 'ci.item_id', '=', 'i.id')
      ->join('checklist_assets as ca', function ($join) use ($assetId) {
        $join->on('ca.checklist_id', '=', 'ci.checklist_id')
          ->where('ca.asset_id', $assetId);
      })
      ->leftJoin('entity_checklist_item_schedules as ecs', 'ecs.checklist_item_id', '=', 'ci.id')
      ->leftJoin('schedules as s', 's.id', '=', 'ecs.schedule_id')
      ->leftJoinSub($this->latestOnTimeResults($assetId), 'latest', function ($join) {
        $join->on('latest.checklist_item_id', '=', 'ci.id');
      })
      ->leftJoin('checklist_item_results as cir', 'cir.id', '=', 'latest.id')
      ->leftJoin('checklist_instances as cs', 'cs.id', '=', 'cir.checklist_instance_id')
      ->where('ci.checklist_id', $checklistId)
      ->get();

    $lookback = now('Asia/Manila')->subDays(90);

    $submitted = DB::table('checklist_item_results')
      ->where('asset_id', $assetId)
      ->whereIn('checklist_item_id', $items->pluck('id'))
      ->whereNotNull('period_start')
      ->get(['checklist_item_id', 'period_start'])
      ->mapWithKeys(fn($r) => [
        $r->checklist_item_id . '|' . Carbon::parse($r->period_start, 'UTC')->timestamp => true,
      ]);

    $results = $items->flatMap(function ($item) use ($lookback, $submitted) {
      $schedule = $item->entitySchedule?->schedule;
      if (!$schedule) return [];

      try {
        $periods = PeriodGeneratorService::generate($lookback, $schedule->toArray());
      } catch (\InvalidArgumentException $e) {
        return [];                                    // hour / multi-year: not period-trackable
      }

      return collect($periods)
        ->filter(fn($p) => $p[1]->isPast())
        ->reject(fn($p) => $submitted->has($item->id . '|' . $p[0]->timestamp))
        ->sortByDesc(fn($p) => $p[0]->timestamp)
        ->take(5)
        ->map(fn($p) => (object) [
          'id'             => $item->id,
          'item_id'        => $item->id, // checklist_items.id; the form submits it as checklist_item_id
          'name'           => $item->name,
          'input_type'     => $item->input_type,
          'allowed_values' => $item->allowed_values,
          'criteria'       => $item->criteria,
          'schedule_id'    => $item->schedule_id,
          'schedule_name'  => $item->schedule_name,
          'created_by'     => $item->created_by,
          'verified_by'    => $item->verified_by,
          'checked_at'     => null,
          'is_no_schedule' => false,
          'is_due'         => 0,
          'period_start'   => $p[0],
          'period_end'     => $p[1],
        ]);
    });

    $results = self::resolveEmployees($results);
    $results = $results->groupBy('item_id');

    return response()->json($results);
  }

  public function getScheduledCheckItems(Request $request)
  {
    $assetId = $request->input('assetId');
    $checklistId = $request->input('checklistId');

    $query =
      ChecklistItem::query()
      ->from('checklist_items as ci')
      ->select([
        'ci.id',
        'i.name',
        'ci.item_id',
        'ci.input_type',
        'ci.allowed_values',
        'cs.verified_by',
        'cs.created_by',
        'ci.criteria',
        's.schedule_name',
        'cir.checked_at',
        DB::raw("s.id IS NULL as is_no_schedule"),
      ])
      ->addSelect(DueScheduleQuery::dueRaw())
      ->addSelect(DueScheduleQuery::overdueRaw())
      ->join('check_items as i', 'ci.item_id', '=', 'i.id')
      ->join('checklist_assets as ca', function ($join) use ($assetId) {
        $join->on('ca.checklist_id', '=', 'ci.checklist_id')
          ->where('ca.asset_id', $assetId);
      })
      ->leftjoin('entity_checklist_item_schedules as ecs', 'ecs.checklist_item_id', '=', 'ci.id')
      ->leftjoin('schedules as s', 's.id', '=', 'ecs.schedule_id')
      ->leftJoinSub($this->latestOnTimeResults($assetId), 'latest', function ($join) {
        $join->on('latest.checklist_item_id', '=', 'ci.id');
      })
      ->leftJoin('checklist_item_results as cir', 'cir.id', '=', 'latest.id')
      ->leftJoin('checklist_instances as cs', 'cs.id', '=', 'cir.checklist_instance_id')
      ->where('ci.checklist_id', $checklistId)
      ->orderBy('s.schedule_name')
      ->orderBy('i.name');

    $results = $query->get();

    $results = self::resolveEmployees($results);

    return response()->json($results);
  }

  public function bulkUpdate(Request $request)
  {
    $rows = $request->all();
    $user = session('emp_data');

    $columnRules = [
      'item_id' => fn($id, $fields) => [
        'required',
        'int',
        Rule::unique('checklist_items', 'item_id')
          ->where('checklist_id', $fields['checklist_id'] ?? null)
          ->ignore(is_numeric($id) ? $id : null),
      ],
      'criteria'       => ['required', 'string', 'max:255'],
      'input_type'     => ['required', 'string', Rule::in(['text', 'number', 'select'])],
      'allowed_values' => 'nullable|array',
      'schedule_id' => fn($id) => [
        'sometimes',
        'nullable',
        'int',
        Rule::exists('schedules', 'id'),
      ],
      'checklist_id' => fn($id) => [
        'required',
        'int',
        Rule::exists('checklists', 'id'),
      ]
    ];

    $rows = array_map(function ($row) use ($user) {
      $row['modified_by'] = $user['emp_id'] ?? null;
      return $row;
    }, $rows);

    $result = DB::transaction(function () use ($user, $rows, $columnRules) {
      $result = (new BulkUpserter(new ChecklistItem(), $columnRules, [], []))->update($rows);
      if (!empty($result['errors'])) return $result;

      $rowsById = collect($rows)->keyBy(fn($r) => (string) $r['id']);
      $touched  = [];
      foreach ($result['updated'] as $m)                     $touched[] = [$m, $rowsById[(string) $m->id] ?? null];
      foreach ($result['insertedByRowId'] as $rowId => $m)   $touched[] = [$m, $rowsById[(string) $rowId] ?? null];

      foreach ($touched as [$item, $row]) {
        if (!$row || !array_key_exists('schedule_id', $row)) continue;

        if ($row['schedule_id']) {
          $item->entitySchedule()->updateOrCreate([], [
            'schedule_id' => $row['schedule_id'],
            'modified_by' => $user['emp_id'] ?? null,
          ]);
        } else {
          $item->entitySchedule()->delete();
        }
      }
      return $result;
    });

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
    ]);
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $entry = ChecklistItem::create([
      ...$validated,
      'modified_by' => $user_id,

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
