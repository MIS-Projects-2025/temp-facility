<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Traits\ParseRequestTrait;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Traits\MassDeletesByIds;
use Illuminate\Support\Facades\Log;
use App\Models\ChecklistInstance;
use Illuminate\Support\Facades\DB;

class ChecklistInstanceController extends Controller
{
  use MassDeletesByIds;
  use ParseRequestTrait;

  public function index(Request $request)
  {
    $verified = $request->boolean('verified');

    $startInput = $request->input('createdAtStart') ?: $request->input('created_at_start');
    $endInput   = $request->input('createdAtEnd')   ?: $request->input('created_at_end');
    $start = $startInput ? Carbon::parse($startInput) : null;
    $end   = $endInput   ? Carbon::parse($endInput)   : null;

    $raw = $request->input('checklistIds');
    $checklistIDs = collect(is_array($raw) ? $raw : explode(',', (string) $raw))
      ->filter(fn($v) => $v !== '' && $v !== null)
      ->map(fn($v) => (int) $v)->unique()->values()->all();

    $perPage = $request->integer('perPage', 30);

    $query = ChecklistInstance::with([
      'results.asset.location',
      'results.item.item',
      'checklist',
      'verifier:EMPLOYID,FIRSTNAME,LASTNAME,JOB_TITLE',
      'creator:EMPLOYID,FIRSTNAME,LASTNAME,JOB_TITLE',
      'approver:EMPLOYID,FIRSTNAME,LASTNAME,JOB_TITLE',
      'allApprovers.employee:EMPLOYID,FIRSTNAME,LASTNAME,JOB_TITLE',
    ])->withCount('lateResults');

    // Resolved = punctual+verified OR late+approved. Must be wrapped in its own
    // group, otherwise the OR leaks and the filters below only hit one branch.
    $query->where(function ($q) use ($verified) {
      $q->where(function ($q) use ($verified) {
        $q->where('submission_type', 'punctual')
          ->when($verified, fn($q) => $q->whereNotNull('verified_at'), fn($q) => $q->whereNull('verified_at'));
      })->orWhere(function ($q) use ($verified) {
        $q->where('submission_type', 'late')
          ->when($verified, fn($q) => $q->whereNotNull('approved_at'), fn($q) => $q->whereNull('approved_at'));
      });
    });

    if ($start && $end) {
      $query->whereBetween('created_at', [$start, $end]);
    }
    if ($checklistIDs) {
      $query->whereIn('checklist_id', $checklistIDs);
    }

    $instances = $query->orderByDesc('created_at')->paginate($perPage);

    $instanceIds = $instances->pluck('id');
    $userId = session('emp_data')['emp_id'] ?? null;

    $approverRows = DB::table('checklist_approvers')
      ->where('instance_type', 'checklist')->whereIn('instance_id', $instanceIds)
      ->get()->groupBy('instance_id');

    $instances->through(function ($instance) use ($approverRows, $userId) {
      $rows     = $approverRows->get($instance->id, collect());
      $mine     = $rows->first(fn($r) => (string) $r->user_id === (string) $userId && $r->status === 'pending');
      $approved = $rows->where('status', 'approved')->pluck('level')->map(fn($l) => (int) $l)->unique();

      $instance->is_approver     = (bool) $mine;
      $instance->can_approve_now = false;
      $instance->deny_reason     = null;

      if ($mine) {
        $level   = (int) $mine->level;
        $blocked = $rows->pluck('level')->map(fn($l) => (int) $l)->unique()
          ->filter(fn($l) => $l < $level)->diff($approved);

        if ($approved->contains($level))  $instance->deny_reason = 'level_already_approved';
        elseif ($blocked->isNotEmpty())   $instance->deny_reason = 'previous_level_pending';
        else                              $instance->can_approve_now = true;
      }
      return $instance;
    });

    $payload = [
      'checklistInstance' => $instances,
      'verified'          => $verified,
      'createdAtStart'    => $start,
      'createdAtEnd'      => $end,
      'checklistIds'      => $checklistIDs,
      'checklists'        => Checklist::all(),
      'perPage'           => $perPage,
      'totalEntries'      => ChecklistInstance::count(),
    ];

    return $request->wantsJson()
      ? response()->json($payload)
      : Inertia::render('ChecklistInstanceList', $payload);
  }

  public function verify(Request $request)
  {
    $ids    = array_values(array_filter($request->all(), 'is_numeric'));
    $userId = session('emp_data.emp_id');

    if (!$userId || empty($ids)) {
      return response()->json(['status' => 'error', 'message' => 'Missing user info or no instances selected.'], 400);
    }

    $updated = ChecklistInstance::whereIn('id', $ids)
      ->where('submission_type', 'punctual')
      ->whereNull('verified_at')
      ->update(['verified_at' => now(), 'verified_by' => $userId]);

    if ($updated === 0) {
      return response()->json([
        'status' => 'error',
        'message' => 'Nothing verified. Late submissions must go through approval.'
      ], 422);
    }

    $skipped = count($ids) - $updated;
    return response()->json([
      'status' => 'success',
      'message' => "$updated verified" . ($skipped ? ", $skipped skipped (late or already verified)." : '.')
    ]);
  }

  public function approve(Request $request, $instanceId)
  {
    $userId = session('emp_data.emp_id');

    return DB::transaction(function () use ($request, $instanceId, $userId) {
      $rows = DB::table('checklist_approvers')
        ->where('instance_type', 'checklist')->where('instance_id', $instanceId)
        ->lockForUpdate()->get();

      $mine = $rows->first(fn($r) => (string) $r->user_id === (string) $userId && $r->status === 'pending');
      if (!$mine) {
        return response()->json(['status' => 'error', 'message' => 'Not authorized'], 403);
      }

      $level    = (int) $mine->level;
      $levels   = $rows->pluck('level')->map(fn($l) => (int) $l)->unique();
      $approved = $rows->where('status', 'approved')->pluck('level')->map(fn($l) => (int) $l)->unique();

      if ($approved->contains($level)) {
        return response()->json(['status' => 'error', 'message' => 'This level is already approved'], 409);
      }
      if ($levels->filter(fn($l) => $l < $level)->diff($approved)->isNotEmpty()) {
        return response()->json(['status' => 'error', 'message' => 'Previous level not yet approved'], 403);
      }

      DB::table('checklist_approvers')->where('id', $mine->id)->update([
        'status' => 'approved',
        'decided_at' => now(),
        'remarks' => $request->input('remarks'),
      ]);

      if ($levels->diff($approved->merge([$level]))->isEmpty()) {   // every level has an approval
        ChecklistInstance::where('id', $instanceId)->update(['approved_by' => $userId, 'approved_at' => now()]);
      }

      return response()->json(['status' => 'ok']);
    });
  }
}
