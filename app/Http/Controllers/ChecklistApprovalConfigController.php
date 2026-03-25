<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\ChecklistApprovalConfig;
use App\Traits\MassDeletesByIds;
use App\Support\CacheKeys;
use App\Services\BulkUpserter;

class ChecklistApprovalConfigController extends Controller
{
  use MassDeletesByIds;

  public function index(Request $request)
  {
    $perPage      = $request->input('perPage', 999);
    $totalEntries = ChecklistApprovalConfig::count();

    $configs = ChecklistApprovalConfig::with('employee:EMPLOYID,FIRSTNAME,LASTNAME,JOB_TITLE')
      ->orderBy('level')
      ->orderBy('user_id')
      ->paginate($perPage)
      ->withQueryString();

    if ($request->wantsJson()) {
      return response()->json([
        'configs'      => $configs,
        'perPage'      => $perPage,
        'totalEntries' => $totalEntries,
      ]);
    }

    return Inertia::render('ChecklistApprovalConfigList', [
      'configs'      => $configs,
      'perPage'      => $perPage,
      'totalEntries' => $totalEntries,
    ]);
  }

  public function bulkUpdate(Request $request)
  {
    $rows = $request->all();

    $columnRules = [
      'user_id' => fn($id) => [
        'required',
        'string',
        Rule::unique('checklist_approval_config', 'user_id')
          ->ignore(is_numeric($id) ? $id : null),
      ],
      'level' => fn($id) => [
        'required',
        'integer',
        'between:1,3',
      ],
      'instance_type' => fn($id) => [
        'nullable',
        'string',
        'max:100',
      ],
    ];

    $bulkUpdater = new BulkUpserter(new ChecklistApprovalConfig(), $columnRules, [], []);
    $result      = $bulkUpdater->update($rows ?? null);

    if (!empty($result['errors'])) {
      return response()->json([
        'status'  => 'error',
        'message' => 'You have ' . count($result['errors']) . ' error/s',
        'data'    => $result['errors'],
      ], 422);
    }

    return response()->json([
      'status'  => 'ok',
      'message' => 'Updated successfully',
    ]);
  }

  public function massGenocide(Request $request)
  {
    return $this->massDeleteByIds(
      $request,
      ChecklistApprovalConfig::class,
      null
    );
  }
}
