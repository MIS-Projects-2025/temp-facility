<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\Asset;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssetsController extends Controller
{
  public function index(Request $request)
  {
    $assets = Asset::query()
      ->with('checklist')
      ->get();

    if ($request->wantsJson()) {
      return response()->json([
        'assets' => $assets,
      ]);
    }

    return Inertia::render('AssetsList', [
      'assets' => $assets,
    ]);
  }

  private function validateEntry(Request $request, $id = null)
  {
    return $request->validate(
      [
        'checklist_id' => 'required|integer|exists:checklists,id',
        'location' => 'required|integer|exists:locations,id',
        'code'      => 'required|string|max:120',
        'properties' => 'nullable|array',
      ],
      [
        'checklist_id.exists' =>
        'The selected checklist was not found. Please double-check and try again.',
      ],
    );
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $entry = Asset::create([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Asset created successfully',
      'data'    => $entry,
    ], 201);
  }

  public function upsert($id = null)
  {
    $item = $id ? Asset::findOrFail($id) : null;

    return Inertia::render('AssetUpsert', [
      'toBeEdit' => $item,
    ]);
  }

  public function update(Request $request, $id)
  {
    $item = Asset::findOrFail($id);

    $validated = $this->validateEntry($request, $id);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $item->update([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Asset updated successfully',
      'data'    => $item,
    ]);
  }

  public function destroy($id)
  {
    try {
      $item = Asset::findOrFail($id);
      $item->delete();

      return response()->json([
        'success' => true,
        'message' => 'Asset deleted successfully',
      ]);
    } catch (ModelNotFoundException $e) {
      return response()->json([
        'status' => 'error',
        'message' => 'Asset not found. Please verify the ID.',
      ], 404);
    }
  }
}
