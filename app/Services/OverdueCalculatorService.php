<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Asset;
use App\Models\ChecklistItemResult;
use App\Constants\DueScheduleQuery;
use Carbon\Carbon;

class OverdueCalculatorService
{
  private static function normalizeJson($v)
  {
    return is_string($v) ? json_decode($v, true) : $v;
  }

  public static function derivePeriod(Carbon $checkedAt, array $schedule): array
  {
    $type        = $schedule['recurrence_type']; // 'interval' or 'daily'
    $unit        = $schedule['interval_unit'];   // 'hour', 'day', 'week', 'month', 'year'
    $value       = (int) $schedule['interval_value'];
    $daysOfWeek  = self::normalizeJson($schedule['days_of_week']);    // json, e.g. [6]
    $daysOfMonth = self::normalizeJson($schedule['days_of_month']);   // json, e.g. [14]
    $nthWeekday  = $schedule['nth_weekday'];     // e.g. 1 (first)
    $weekdayOf   = $schedule['weekday_of_month']; // e.g. 1 (Monday)
    $dayTimes    = self::normalizeJson($schedule['day_times']);       // json, e.g. ["00:00", "12:00"]

    // Twice a day — check day_times first before anything else
    if ($type === 'daily' && !empty($dayTimes)) {
      $times = collect($dayTimes)
        ->map(fn($t) => Carbon::parse($checkedAt->toDateString() . ' ' . $t, $checkedAt->timezone))
        ->sort()
        ->values();

      $periodStart = null;
      $periodEnd   = null;

      foreach ($times as $i => $windowStart) {
        $windowEnd = isset($times[$i + 1])
          ? $times[$i + 1]->copy()->subSecond()
          : Carbon::parse($checkedAt->toDateString() . ' 23:59:59', $checkedAt->timezone);

        if ($checkedAt->between($windowStart, $windowEnd)) {
          $periodStart = $windowStart;
          $periodEnd   = $windowEnd;
          break;
        }
      }

      return [$periodStart, $periodEnd];
    }

    switch ($unit) {
      case 'day':
        $start = $checkedAt->copy()->startOfDay();
        $end   = $checkedAt->copy()->endOfDay();
        break;

      case 'week':
        // Specific day of week e.g. every Saturday
        if (!empty($daysOfWeek)) {
          $dow   = (int) $daysOfWeek[0]; // 0=Sun, 6=Sat (Carbon convention)
          $start = $checkedAt->copy()->startOfDay()->dayOfWeek === $dow
            ? $checkedAt->copy()->startOfDay()
            : $checkedAt->copy()->previous($dow)->startOfDay();
          $end   = $start->copy()->endOfDay();
        } else {
          // Generic weekly Mon–Sun
          $start = $checkedAt->copy()->startOfWeek();
          $end   = $checkedAt->copy()->endOfWeek();
        }
        break;

      case 'month':
        // First Nth weekday of month e.g. first Monday
        if (!empty($nthWeekday) && !empty($weekdayOf)) {
          $date  = $checkedAt->copy()->startOfMonth()->nthOfMonth((int) $nthWeekday, ((int) $weekdayOf) % 7);
          if (!$date) {
            throw new \InvalidArgumentException('Nth weekday does not exist in this month.');
          }
          $start = $date->copy()->startOfDay();
          $end   = $date->copy()->endOfDay();
          break;
        }

        // Specific day of month e.g. every 14th
        if (!empty($daysOfMonth)) {
          $day   = $daysOfMonth[0];
          $start = $checkedAt->copy()->startOfMonth()->addDays(min((int) $day, $checkedAt->daysInMonth) - 1);
          $end   = $start->copy()->endOfDay();
          break;
        }

        // Generic monthly
        $start = $checkedAt->copy()->startOfMonth();
        $end   = $checkedAt->copy()->endOfMonth();
        break;

      case 'year':
        if ($value === 1) {
          // Annually
          $start = $checkedAt->copy()->startOfYear();
          $end   = $checkedAt->copy()->endOfYear();
        } elseif ($value === 3 || $value === 5) {
          // Multi-year — anchor from schedule assignment created_at
          // Caller must pass $anchorDate for these
          throw new \InvalidArgumentException(
            "Multi-year schedules require an anchor date. Handle separately."
          );
        } else {
          throw new \InvalidArgumentException("Unknown interval_value: {$value}");
        }
        break;

      case 'hour':
        // Cannot derive from checked_at alone
        throw new \InvalidArgumentException(
          "Hour-based schedules cannot derive period from checked_at alone."
        );

      default:
        throw new \InvalidArgumentException("Unknown interval_unit: {$unit}");
    }

    // Quarterly — override the monthly generic case
    if ($unit === 'month' && $value === 3) {
      $start = $checkedAt->copy()->startOfQuarter();
      $end   = $checkedAt->copy()->endOfQuarter();
    }

    // Semi-annually
    if ($unit === 'month' && $value === 6) {
      $start = $checkedAt->copy()->startOfYear()->addMonths($checkedAt->month <= 6 ? 0 : 6);
      $end = $start->copy()->addMonths(6)->subSecond();
    }

    return [$start, $end];
  }
}
