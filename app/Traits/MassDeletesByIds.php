<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

trait MassDeletesByIds
{
  protected function massDeleteByIds(
    Request $request,
    string $modelClass,
    ?string $cacheKey = null
  ) {
    $ids = $request->input('ids');

    if (!is_array($ids) || empty($ids)) {
      return response()->json([
        'status' => 'error',
        'message' => 'No IDs provided.',
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
