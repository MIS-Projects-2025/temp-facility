<?php

namespace App\Services;

use Carbon\Carbon;

class PeriodGeneratorService
{
  public static function generate(Carbon $anchor, array $schedule): array
  {
    $now     = Carbon::now('Asia/Manila');
    $current = $anchor->copy();
    $periods = [];
    $seen    = [];

    while ($current < $now) {
      [$start, $end] = OverdueCalculatorService::derivePeriod($current, $schedule);

      if (!$start || !$end) {                       // between daily windows
        $current = $current->copy()->addHour()->startOfHour();
        continue;
      }

      if (!isset($seen[$start->timestamp])) {
        $seen[$start->timestamp] = true;
        $periods[] = [$start, $end];
      }

      $next    = $end->copy()->addSecond();
      $current = $next->gt($current)                // guarantee forward progress
        ? $next
        : $current->copy()->addDay()->startOfDay();
    }

    return $periods;
  }
}
