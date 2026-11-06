<?php

namespace App\Traits;

use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

trait MassDeletesByIds
{
  protected function massDeleteByIds(
    Request $request,
    string $modelClass,
    ?string $cacheKey = null
  ) {
    // unsaved grid rows ("new-1") have no DB id
    $ids = collect($request->input('ids'))
      ->filter(fn($id) => is_numeric($id))
      ->unique()
      ->values()
      ->all();

    if (empty($ids)) {
      return response()->json([
        'status' => 'error',
        'message' => 'No saved rows selected.',
      ], 422);
    }

    if (!is_subclass_of($modelClass, Model::class)) {
      abort(500, 'Invalid model class.');
    }

    try {
      $deleted = DB::transaction(function () use ($modelClass, $ids) {
        $n = 0;
        $modelClass::whereIn('id', $ids)->get()->each(function ($m) use (&$n) {
          $m->delete();
          $n++;
        });
        return $n;
      });
    } catch (DomainException $e) {
      // model-level "still in use" guard
      return response()->json([
        'status' => 'error',
        'message' => $e->getMessage(),
      ], 409);
    } catch (QueryException $e) {
      if ($e->getCode() === '23000') {
        return response()->json([
          'status' => 'error',
          'message' => 'Some items cannot be deleted because they are still used elsewhere.'
        ], 400);
      }
      throw $e;
    }

    if ($cacheKey) {
      Cache::forget($cacheKey);
    }

    return response()->json([
      'success' => true,
      'deleted_count' => $deleted,
    ]);
  }
}
