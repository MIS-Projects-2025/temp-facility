<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\Checklist;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Support\CacheKeys;

class ChecklistsController extends Controller
{
  public function index(Request $request)
  {
    $checklists = Checklist::query()
      ->with('checklistItems.item')
      ->get();

    if ($request->wantsJson()) {
      return response()->json([
        'checklist' => $checklists,
      ]);
    }

    return Inertia::render('ChecklistList', [
      'checklist' => $checklists,
    ]);
  }

  private function validateEntry(Request $request, $id = null)
  {
    return $request->validate(
      [
        'name' => [
          'required',
          'string',
          'max:255',
          Rule::unique('checklists')->where(function ($query) use ($request) {
            return $query->where('name', $request->name);
          })->ignore($id),
        ],
        'description'      => 'nullable|string',
      ],
      [
        'name.unique' =>
        'The name provided already exists.',
      ],
    );
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $entry = Checklist::create([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Item created successfully',
      'data'    => $entry,
    ], 201);
  }

  public function upsert($id = null)
  {
    $item = $id ? Checklist::findOrFail($id) : null;

    return Inertia::render('ChecklistUpsert', [
      'toBeEdit' => $item,
    ]);
  }

  public function update(Request $request, $id)
  {
    $item = Checklist::findOrFail($id);

    $validated = $this->validateEntry($request, $id);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $item->update([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Item updated successfully',
      'data'    => $item,
    ]);
  }

  public function destroy($id)
  {
    try {
      $item = Checklist::findOrFail($id);
      $item->delete();

      return response()->json([
        'success' => true,
        'message' => 'Item deleted successfully',
      ]);
    } catch (ModelNotFoundException $e) {
      return response()->json([
        'status' => 'error',
        'message' => 'Item not found. Please verify the ID.',
      ], 404);
    }
  }

  public function getAllChecklists(Request $request)
  {

    return Cache::remember(CacheKeys::checklistsAll(), CacheKeys::defaultCacheDuration(), function () {
      // return Checklist::all();
      return Checklist::select('id', 'name')->get();
    });
  }
}
