<?php

namespace App\Services;

use Illuminate\Support\Collection;

class RunningHoursService
{
  private static function computeRunningHours(array $item): array
  {
    $first = $item['first']->item_status;
    $latest = $item['latest']->item_status;
    $isValid = is_numeric($first) && is_numeric($latest);

    return array_merge($item, [
      'running_hours' => $isValid ? (float)$latest - (float)$first : null,
      'running_hours_invalid' => !$isValid,
    ]);
  }

  public static function enrichWithRunningHours(Collection $results, string $slug = 'running_hours'): Collection
  {
    return $results->map(function ($assetItems) use ($slug) {
      return $assetItems->map(function ($item) use ($slug) {
        if ($item['first']->item_slug !== $slug) {
          return $item;
        }

        return self::computeRunningHours($item);
      });
    });
  }
}
