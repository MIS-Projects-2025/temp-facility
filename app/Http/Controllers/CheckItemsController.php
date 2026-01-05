<?php

namespace App\Http\Controllers;

use App\Traits\ValidateEmployeeExistenceTrait;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\CheckItem;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CheckItemsController extends Controller
{
  public function index(Request $request)
  {
    $search = $request->input('search', '');
    $perPage = $request->input('perPage', 100);
    $totalEntries = CheckItem::count();

    $checkItems = CheckItem::query()
      ->when($search, function ($query, $search) {
        $query->where(function ($q) use ($search) {
          $q->orWhere('name', 'like', "%{$search}%");
        });
      })
      ->orderBy('name')
      ->paginate($perPage)
      ->withQueryString();

    if ($request->wantsJson()) {
      return response()->json([
        'checklistItems' => $checkItems,
        'search' => $search,
        'perPage' => $perPage,
        'totalEntries' => $totalEntries,
      ]);
    }

    return Inertia::render('CheckItemsList', [
      'checklistItems' => $checkItems,
      'search' => $search,
      'perPage' => $perPage,
      'totalEntries' => $totalEntries,
    ]);
  }

  private function validateEntry(Request $request, $id = null)
  {
    return $request->validate(
      [
        'name'      => 'required|string|max:255',
        'description' => 'nullable|string',
      ],
    );
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $entry = CheckItem::create([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Check Item created successfully',
      'data'    => $entry,
    ], 201);
  }

  public function upsert($id = null)
  {
    $item = $id ? CheckItem::findOrFail($id) : null;

    return Inertia::render('CheckItemUpsert', [
      'toBeEdit' => $item,
    ]);
  }

  public function update(Request $request, $id)
  {
    $item = CheckItem::findOrFail($id);

    $validated = $this->validateEntry($request, $id);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $item->update([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    return response()->json([
      'message' => 'Check Item updated successfully',
      'data'    => $item,
    ]);
  }

  public function destroy($id)
  {
    try {
      $item = CheckItem::findOrFail($id);
      $item->delete();

      return response()->json([
        'success' => true,
        'message' => 'Check Item deleted successfully',
      ]);
    } catch (ModelNotFoundException $e) {
      return response()->json([
        'status' => 'error',
        'message' => 'Check Item not found. Please verify the ID.',
      ], 404);
    }
  }
}
