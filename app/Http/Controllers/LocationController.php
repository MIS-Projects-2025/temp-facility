<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Support\CacheKeys;
use Faker\Factory as Faker;

class LocationController extends Controller
{
  // public function index(Request $request)
  // {
  //   $faker = Faker::create();

  //   $allLocations = [];

  //   for ($i = 1; $i <= 1000; $i++) {
  //     $allLocations[] = [
  //       'id' => $i,
  //       'location_name' => $faker->city(),       // random city name
  //       'created_by' => $faker->numberBetween(1, 100),
  //       'created_at' => $faker->dateTimeThisYear()->format('Y-m-d H:i:s'),
  //       'modified_by' => $faker->numberBetween(1, 100),
  //       'modified_at' => $faker->dateTimeThisYear()->format('Y-m-d H:i:s'),
  //     ];
  //   }

  //   return Inertia::render('LocationList', [
  //     'locations' => $allLocations,
  //   ]);
  // }

  public function index(Request $request)
  {
    $allLocations = Location::query()
      ->get();

    return Inertia::render('LocationList', [
      'locations' => $allLocations,
    ]);
  }

  private function validateEntry(Request $request, $id = null)
  {
    return $request->validate(
      [
        'location_name' => [
          'required',
          'string',
          'max:255',
          Rule::unique((new Location())->getTable())->where(function ($query) use ($request) {
            return $query->where('location_name', $request->location_name);
          })->ignore($id),
        ],
      ],
      [
        'location_name.unique' =>
        'The location name provided already exists.',
      ]
    );
  }

  public function store(Request $request)
  {
    $validated = $this->validateEntry($request);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $entry = Location::create([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    Cache::forget(CacheKeys::locationsAll());

    return response()->json([
      'message' => 'Location created successfully',
      'data'    => $entry,
    ], 201);
  }

  public function upsert($id = null)
  {
    $item = $id ? Location::findOrFail($id) : null;

    return Inertia::render('LocationUpsert', [
      'toBeEdit' => $item,
    ]);
  }

  public function update(Request $request, $id)
  {
    $item = Location::findOrFail($id);

    $validated = $this->validateEntry($request, $id);
    $user_id = session('emp_data')['emp_id'] ?? null;

    $item->update([
      ...$validated,
      'modified_by' => $user_id,
      'modified_at' => Carbon::now(),
    ]);

    Cache::forget(CacheKeys::locationsAll());

    return response()->json([
      'message' => 'Location updated successfully',
      'data'    => $item,
    ]);
  }

  public function bulkUpdate(Request $request)
  {
    $rows = $request->all();
    $user = session('emp_data');

    DB::transaction(function () use ($rows, $user) {

      foreach ($rows as $id => $fields) {

        if (empty($fields)) {
          continue;
        }

        $model = Location::find($id);

        if (!$model) {
          continue;
        }

        $updateData = [];

        foreach ($fields as $column => $value) {
          $updateData[$column] = $value;
        }

        $updateData['modified_by'] = $user['emp_id'] ?? null;

        if (!empty($updateData)) {
          $model->update($updateData);
        }
      }
    });

    Cache::forget(CacheKeys::locationsAll());
    return response()->json(['status' => 'ok']);
  }

  public function destroy($id)
  {
    try {
      $item = Location::findOrFail($id);
      $item->delete();

      Cache::forget(CacheKeys::locationsAll());
      return response()->json([
        'success' => true,
        'message' => 'Location deleted successfully',
      ]);
    } catch (ModelNotFoundException $e) {
      return response()->json([
        'status' => 'error',
        'message' => 'Location not found. Please verify the ID.',
      ], 404);
    }
  }

  public function massGenocide(Request $request)
  {
    $ids = $request->input('ids'); // expect array

    if (!is_array($ids) || empty($ids)) {
      return response()->json([
        'status' => 'error',
        'message' => 'No IDs provided.',
      ], 422);
    }

    $deleted = Location::whereIn('id', $ids)->delete();

    Cache::forget(CacheKeys::locationsAll());

    return response()->json([
      'success' => true,
      'deleted_count' => $deleted,
    ]);
  }


  public function getAllLocation(Request $request)
  {

    return Cache::remember(CacheKeys::locationsAll(), CacheKeys::defaultCacheDuration(), function () {
      // return Location::all();
      return Location::select('id', 'location_name')->get();
    });
  }
}
