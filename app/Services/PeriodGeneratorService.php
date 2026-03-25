<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Asset;
use App\Models\ChecklistItemResult;
use App\Constants\DueScheduleQuery;
use Carbon\Carbon;

class PeriodGeneratorService
{
  public static function generate(Carbon $anchor, array $schedule): array
  {
    $current = $anchor->copy();
    $periods = [];

    while ($current < Carbon::now('Asia/Manila')) {
      [$periodStart, $periodEnd] = OverdueCalculatorService::derivePeriod($current, $schedule);
      $periods[] = [$periodStart, $periodEnd];
      $current = $periodEnd->copy()->addSecond();
    }

    return $periods;
  }
}
