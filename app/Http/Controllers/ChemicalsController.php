<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\Chemicals;

class ChemicalsController extends Controller
{
  public function index(Request $request)
  {
    $search = $request->input('search', '');
    $perPage = $request->input('perPage', 10);
    $totalEntries = Chemicals::count();

    $chemicals = Chemicals::query()
      ->when($search, function ($query, $search) {
        $query->where('name', 'like', "%{$search}%");
      })
      ->orderBy('name')
      ->paginate($perPage)
      ->withQueryString();

    return Inertia::render('ChemicalList', [
      'chemicals' => $chemicals,
      'search' => $search,
      'perPage' => $perPage,
      'totalEntries' => $totalEntries,
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
          Rule::unique('chemicals', 'name')->ignore($id),
        ],
        'description' => 'nullable|string',
      ],
      [
        'name.unique' => 'Chemical name already exists.',
      ]
    );
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);

    $entry = Chemicals::create($validated);

    return response()->json([
      'message' => 'Entry added successfully',
      'data' => $entry,
    ]);
  }

  public function upsert($id = null)
  {
    $chemicalToBeEdit = $id ? Chemicals::findOrFail($id) : null;

    return Inertia::render('ChemicalUpsert', [
      'toBeEdit' => $chemicalToBeEdit,
    ]);
  }

  public function update(Request $request, $id)
  {
    $chemical = Chemicals::findOrFail($id);

    $validated = $this->validateEntry($request, $id);
    $chemical->update($validated);

    return response()->json([
      'message' => 'Entry updated successfully',
      'data' => $chemical,
    ]);
  }

  public function destroy($id)
  {
    $entry = Chemicals::findOrFail($id);
    $entry->delete();

    return response()->json([
      'success' => true,
      'message' => 'Entry deleted successfully',
    ]);
  }
}
