<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Traits\ParseRequestTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\AssetPmSchedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use App\Traits\MassDeletesByIds;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\ChecklistInstance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ChecklistInstanceController extends Controller
{
  use MassDeletesByIds;
  use ParseRequestTrait;

  public function index(Request $request)
  {
    $query = ChecklistInstance::with([
      'results.asset.location',
      'results.item.item',
      'checklist',
      'verifier:EMPLOYID,FIRSTNAME,LASTNAME,JOB_TITLE',
      'creator:EMPLOYID,FIRSTNAME,LASTNAME,JOB_TITLE',
      'approver:EMPLOYID,FIRSTNAME,LASTNAME,JOB_TITLE',
      'allApprovers.employee:EMPLOYID,FIRSTNAME,LASTNAME,JOB_TITLE',
    ])->withCount('lateResults');
    $verified = $request->filled('verified')
      ? filter_var($request->verified, FILTER_VALIDATE_BOOLEAN)
      : false;
    $hasCreatedAtStart = $request->filled('created_at_start') ? Carbon::parse($request->created_at_start) : null;
    $hasCreatedAtEnd = $request->filled('created_at_end') ? Carbon::parse($request->created_at_end) : null;
    $hasChecklistIds = $request->filled('checklistIds');
    $checklistIDs = $this->parseChecklists($request, 'checklistIds');
    $perPage = $request->integer('perPage', 30);
    $allChecklist = Checklist::all();

    $user = session('emp_data');
    $userId = $user['emp_id'] ?? null;

    $totalEntries = $query->count();

    $query->when($verified, function ($q) {
      // Resolved: punctual verified OR late approved
      $q->where(function ($q) {
        $q->where('submission_type', 'punctual')->whereNotNull('verified_at');
      })->orWhere(function ($q) {
        $q->where('submission_type', 'late')->whereNotNull('approved_at');
      });
    })
      ->when(!$verified, function ($q) {
        // Unresolved: punctual not verified OR late not approved
        $q->where(function ($q) {
          $q->where('submission_type', 'punctual')->whereNull('verified_at');
        })->orWhere(function ($q) {
          $q->where('submission_type', 'late')->whereNull('approved_at');
        });
      });
    // $query->when($verified, fn($q) => $q->whereNotNull('verified_at'))
    //   ->when(!$verified, fn($q) => $q->whereNull('verified_at'));

    if ($hasCreatedAtStart && $hasCreatedAtEnd) {
      $hasCreatedAtStart = Carbon::parse($request->created_at_start);
      $hasCreatedAtEnd   = Carbon::parse($request->created_at_end);
      $query->whereBetween('created_at', [$hasCreatedAtStart, $hasCreatedAtEnd]);
    }

    if ($hasChecklistIds) {
      $query->whereIn('checklist_id', $checklistIDs);
    }

    $instances = $query->orderByDesc('created_at')
      ->paginate($perPage);

    Log::info("Instances: " . json_encode($instances));

    $instanceIds = $instances->pluck('id');

    // User's pending approver rows for this page
    $userApproverRows = DB::table('checklist_approvers')
      ->where('instance_type', 'checklist')
      ->where('user_id', $userId)
      ->where('status', 'pending')
      ->whereIn('instance_id', $instanceIds)
      ->get()
      ->keyBy('instance_id');

    // All approved levels per instance — { instance_id => [1, 2, ...] }
    $approvedLevels = DB::table('checklist_approvers')
      ->where('instance_type', 'checklist')
      ->where('status', 'approved')
      ->whereIn('instance_id', $instanceIds)
      ->get()
      ->groupBy('instance_id')
      ->map(fn($rows) => $rows->pluck('level')->all());

    $instances->through(function ($instance) use ($userApproverRows, $approvedLevels) {
      $approverRow = $userApproverRows->get($instance->id);
      $approved    = $approvedLevels->get($instance->id, []);

      $instance->is_approver     = !is_null($approverRow);
      $instance->can_approve_now = $instance->is_approver
        && !in_array($approverRow->level, $approved)
        && (
          $approverRow->level === 1 ||
          in_array($approverRow->level - 1, $approved)
        );

      $instance->deny_reason = null;
      if ($instance->is_approver && !$instance->can_approve_now) {
        $instance->deny_reason = in_array($approverRow->level, $approved)
          ? 'level_already_approved'
          : 'previous_level_pending';
      }
      return $instance;
    });

    if ($request->wantsJson()) {
      return response()->json([
        'checklistInstance' => $instances,
        'verified' => $verified,
        'createdAtStart' => $hasCreatedAtStart ?? null,
        'createdAtEnd' => $hasCreatedAtEnd ?? null,
        'checklistIds' => $hasChecklistIds ? $checklistIDs : [],
        'checklists' => $allChecklist,
        'perPage' => $perPage,
        'totalEntries' => $totalEntries,
      ]);
    }

    return Inertia::render('ChecklistInstanceList', [
      'checklistInstance' => $instances,
      'verified' => $verified,
      'createdAtStart' => $hasCreatedAtStart ?? null,
      'createdAtEnd' => $hasCreatedAtEnd ?? null,
      'checklistIds' => $hasChecklistIds ? $checklistIDs : [],
      'checklists' => $allChecklist,
      'perPage' => $perPage,
      'totalEntries' => $totalEntries,
    ]);
  }

  public function verify(Request $request)
  {
    $ids = $request->all();
    $user = session('emp_data');
    $userId = $user['emp_id'] ?? null;

    if (!$userId || empty($ids)) {
      return response()->json([
        'message' => 'Missing user info or no instances selected.'
      ], 400);
    }

    // Update all instances at once
    $updated = ChecklistInstance::whereIn('id', $ids)
      ->update([
        'verified_at' => now(),
        'verified_by' => $userId,
      ]);

    $hasLate = ChecklistInstance::whereIn('id', $ids)
      ->where('submission_type', 'late')
      ->exists();

    if ($hasLate) {
      return response()->json(['status' => 'error', 'message' => 'Late submissions must go through the approval process.'], 403);
    }

    return response()->json([
      'status' => 'success',
      'message' => "$updated instance(s) verified successfully."
    ]);
  }

  public function approve(Request $request, $instanceId)
  {
    $userId = session('emp_data')['emp_id'];

    $approver = DB::table('checklist_approvers')
      ->where('instance_id', $instanceId)
      ->where('user_id', $userId)
      ->where('status', 'pending')
      ->first();

    // Is this user even an approver for this instance?
    if (!$approver) {
      return response()->json(['error' => 'Not authorized'], 403);
    }

    // Is the previous level done?
    if ($approver->level > 1) {
      $previousLevelApproved = DB::table('checklist_approvers')
        ->where('instance_id', $instanceId)
        ->where('level', $approver->level - 1)
        ->where('status', 'approved')
        ->exists();

      if (!$previousLevelApproved) {
        return response()->json(['error' => 'Previous level not yet approved'], 403);
      }
    }

    // Stamp the decision
    DB::table('checklist_approvers')
      ->where('id', $approver->id)
      ->update([
        'status'     => 'approved',
        'decided_at' => now(),
        'remarks'    => $request->input('remarks'),
      ]);

    // If level 3, stamp the instance
    if ($approver->level === 3) {
      ChecklistInstance::where('id', $instanceId)->update([
        'approved_by' => $userId,
        'approved_at' => now(),
      ]);
    }

    return response()->json(['status' => 'ok']);
  }
}
