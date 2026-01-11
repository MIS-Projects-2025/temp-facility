<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\AssetPmSchedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

class AssetPmSchedulesController extends Controller
{
  public function index(Request $request)
  {
    $assetPmSchedules = AssetPmSchedule::query()
      ->with('assets')
      ->get();

    if ($request->wantsJson()) {
      return response()->json([
        'assetPmSchedules' => $assetPmSchedules,
      ]);
    }

    return Inertia::render('AssetPmSchedulesList', [
      'assetPmSchedules' => $assetPmSchedules,
    ]);
  }

  private function validateEntry(Request $request, $id = null)
  {
    return $request->validate(
      [
        'schedule_id' => 'required|integer|exists:schedules,id',
        'asset_id' => [
          'required',
          'integer',
          'exists:assets,id',
          Rule::unique((new AssetPmSchedule())->getTable())->where(function ($query) use ($request) {
            return $query->where('asset_id', $request->asset_id);
          })->ignore($id),
        ],
      ],
      [
        'schedule_id.exists' => 'The selected schedule was not found. Please double-check and try again.',
        'asset_id.exists' => 'The selected asset was not found. Please double-check and try again.',
        'asset_id.unique' => 'This asset already has a schedule assigned. Please choose a different asset.',
      ]
    );
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $entry = AssetPmSchedule::create([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'AssetPmSchedule created successfully',
      'data'    => $entry,
    ], 201);
  }

  public function upsert($id = null)
  {
    $item = $id ? AssetPmSchedule::findOrFail($id) : null;

    return Inertia::render('AssetPmScheduleUpsert', [
      'toBeEdit' => $item,
    ]);
  }

  public function update(Request $request, $id)
  {
    $item = AssetPmSchedule::findOrFail($id);

    $validated = $this->validateEntry($request, $id);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $item->update([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'AssetPmSchedule updated successfully',
      'data'    => $item,
    ]);
  }

  public function destroy($id)
  {
    try {
      $item = AssetPmSchedule::findOrFail($id);
      $item->delete();

      return response()->json([
        'success' => true,
        'message' => 'AssetPmSchedule deleted successfully',
      ]);
    } catch (ModelNotFoundException $e) {
      return response()->json([
        'status' => 'error',
        'message' => 'AssetPmSchedule not found. Please verify the ID.',
      ], 404);
    }
  }
}
