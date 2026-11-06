<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use App\Models\UtilityTrash;
use Carbon\Carbon;

class UtilityTrashController extends Controller
{
  public function index(Request $request)
  {
    $search = $request->input('search', '');
    $startDate = $request->filled('startDate') ? Carbon::parse($request->startDate) : null;
    $endDate = $request->filled('endDate') ? Carbon::parse($request->endDate) : null;
    $isVerified = filter_var($request->input('isVerified'), FILTER_VALIDATE_BOOLEAN);
    $isNotVerified = filter_var($request->input('isNotVerified'), FILTER_VALIDATE_BOOLEAN);
    $perPage = $request->input('perPage', 100);
    $totalEntries = UtilityTrash::count();

    $utilityTrash = UtilityTrash::query()
      ->with([
        'performedBy:EMPLOYID,EMPNAME,JOB_TITLE,DEPARTMENT',
        'verifiedBy:EMPLOYID,EMPNAME,JOB_TITLE,DEPARTMENT',
      ])
      ->when($search, function ($query, $search) {
        $query->where(function ($q) use ($search) {
          $q->orWhere('performed_by', 'like', "%{$search}%");
          $q->orWhere('verified_by', 'like', "%{$search}%");
        });
      })
      ->when(!$isVerified || !$isNotVerified, function ($query) use ($isVerified, $isNotVerified) {
        if ($isVerified && !$isNotVerified) {
          $query->whereNotNull('verified_by');
        } elseif (!$isVerified && $isNotVerified) {
          $query->whereNull('verified_by');
        }
      })
      ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
        // 'date' is stored as Manila wall-clock time (see perform())
        $query->whereBetween('date', [
          $startDate->copy()->setTimezone('Asia/Manila')->toDateTimeString(),
          $endDate->copy()->setTimezone('Asia/Manila')->toDateTimeString(),
        ]);
      })
      ->orderBy('date', 'desc')
      ->paginate($perPage)
      ->withQueryString();

    return Inertia::render('UtilityTrash/UtilityTrashList', [
      'utilityTrash' => $utilityTrash,
      'timeRange' => [
        ['startHour' => 9,    'endHour' => 9.5],
        ['startHour' => 12.5, 'endHour' => 13],
        ['startHour' => 17,   'endHour' => 17.5],
        ['startHour' => 21,   'endHour' => 21.5],
        ['startHour' => 0.5,  'endHour' => 1],
        ['startHour' => 5,    'endHour' => 5.5],
      ],
      'isNotVerified' => $isNotVerified,
      'isVerified' => $isVerified,
      'startDate' => $startDate,
      'endDate' => $endDate,
      'search' => $search,
      'perPage' => $perPage,
      'totalEntries' => $totalEntries,
    ]);
  }

  public function bulkVerify(Request $request)
  {
    $userId = session('emp_data.emp_id');
    abort_if(!$userId, 401, 'Session expired. Please log in again.');

    $ids = collect(Arr::wrap($request->input('id')))->filter()->values()->all();

    if (empty($ids)) {
      return response()->json(['status' => 'error', 'message' => 'No entry selected.'], 422);
    }

    $updated = UtilityTrash::whereKey($ids)
      ->whereNull('verified_by')
      ->update(['verified_by' => $userId]);

    if ($updated === 0) {
      return response()->json(['status' => 'error', 'message' => 'Entry is already verified.'], 409);
    }

    return response()->json(['status' => 'ok']);
  }

  public function perform(Request $request)
  {
    $request->validate(['date' => 'required|date']);

    $userId = session('emp_data.emp_id');
    abort_if(!$userId, 401, 'Session expired. Please log in again.');

    UtilityTrash::insert([
      'date' => Carbon::parse($request->input('date'))->setTimezone('Asia/Manila'),
      'performed_by' => $userId,
    ]);

    return response()->json(['status' => 'ok']);
  }
}
