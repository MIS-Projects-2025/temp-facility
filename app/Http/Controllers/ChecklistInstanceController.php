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
      // 'verifier:FIRSTNAME,LASTNAME,JOB_TITLE'
      // 'verifier'
    ]);
    $hasVerified = $request->input('verified', false);
    $hasCreatedAtStart = $request->filled('created_at_start') ? Carbon::parse($request->created_at_start) : null;
    $hasCreatedAtEnd = $request->filled('created_at_end') ? Carbon::parse($request->created_at_end) : null;
    $hasChecklistIds = $request->filled('checklistIds');
    $checklistIDs = $this->parseChecklists($request, 'checklistIds');
    $perPage = $request->integer('perPage', 30);
    $allChecklist = Checklist::all();

    // might be troublesome
    $totalEntries = $query->count();

    if ($hasVerified) {
      $hasVerified = filter_var($request->verified, FILTER_VALIDATE_BOOLEAN);
      $query->when($hasVerified, fn($q) => $q->whereNotNull('verified_at'))
        ->when(!$hasVerified, fn($q) => $q->whereNull('verified_at'));
    }

    if ($hasCreatedAtStart && $hasCreatedAtEnd) {
      Log::info("created_at_start: {$hasCreatedAtStart}, created_at_end: {$hasCreatedAtEnd}");
      Log::info("created_at_start: {$request->created_at_start}, created_at_end: {$request->created_at_end}");
      $hasCreatedAtStart = Carbon::parse($request->created_at_start);
      $hasCreatedAtEnd   = Carbon::parse($request->created_at_end);
      $query->whereBetween('created_at', [$hasCreatedAtStart, $hasCreatedAtEnd]);
    }

    if ($hasChecklistIds) {
      $query->whereIn('checklist_id', $checklistIDs);
    }

    $instances = $query->orderByDesc('created_at')
      ->paginate($perPage);

    if ($request->wantsJson()) {
      return response()->json([
        'checklistInstance' => $instances,
        'verified' => $hasVerified,
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
      'verified' => $hasVerified,
      'createdAtStart' => $hasCreatedAtStart ?? null,
      'createdAtEnd' => $hasCreatedAtEnd ?? null,
      'checklistIds' => $hasChecklistIds ? $checklistIDs : [],
      'checklists' => $allChecklist,
      'perPage' => $perPage,
      'totalEntries' => $totalEntries,
    ]);
    // if ($request->wantsJson()) {
    //   return response()->json(['checklist_instance' => $instances]);
    // }

    // return Inertia::render('ChecklistInstanceList', ['checklist_instance' => $instances]);
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

    return response()->json([
      'status' => 'success',
      'message' => "$updated instance(s) verified successfully."
    ]);
  }

  // public function index(Request $request)
  // {
  //   $instances = DB::table('checklist_instances as ci')
  //     ->select([
  //       'ci.id',
  //       'ci.checklist_id',
  //       'ci.notes',
  //       'ci.created_at',
  //       'ci.created_by',
  //       'ci.verified_at',
  //       'ci.verified_by',
  //       DB::raw("
  //           JSON_ARRAYAGG(
  //               JSON_OBJECT(
  //                   'checked_by', cir.checked_by,
  //                   'checked_at', cir.checked_at,
  //                   'remarks', cir.remarks,
  //                   'asset_id', cir.asset_id,
  //                   'asset_name', a.code,
  //                   'status', cir.item_status,
  //                   'criteria', i.criteria
  //               )
  //           ) as results
  //       ")
  //     ])
  //     ->join('checklist_item_results as cir', 'cir.checklist_instance_id', '=', 'ci.id')
  //     ->leftJoin('assets as a', 'a.id', '=', 'cir.asset_id')
  //     ->leftJoin('checklist_items as i', 'i.id', '=', 'cir.checklist_item_id')
  //     ->groupBy([
  //       'ci.id',
  //       'ci.checklist_id',
  //       'ci.notes',
  //       'ci.created_at',
  //       'ci.created_by',
  //       'ci.verified_at',
  //       'ci.verified_by',
  //     ]);

  //   if ($request->filled('verified')) {
  //     $verified = filter_var($request->verified, FILTER_VALIDATE_BOOLEAN);

  //     $instances->when(
  //       $verified,
  //       fn($q) => $q->whereNotNull('verified_at'),
  //       fn($q) => $q->whereNull('verified_at')
  //     );
  //   }

  //   if ($request->filled('created_to')) {
  //     $instances->whereDate('created_at', '<=', $request->created_to);
  //   }

  //   $result = $instances
  //     ->orderByDesc('created_at')
  //     ->paginate($request->integer('perPage', 30));

  //   if ($request->wantsJson()) {
  //     return response()->json([
  //       'checklist_instance' => $result,
  //     ]);
  //   }

  //   return Inertia::render('ChecklistInstanceList', [
  //     'checklist_instance' => $result,
  //   ]);
  // }
}
