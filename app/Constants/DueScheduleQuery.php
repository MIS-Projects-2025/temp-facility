<?php

namespace App\Constants;

use Illuminate\Support\Facades\DB;

class DueScheduleQuery
{
  private const MANILA_NOW        = "CONVERT_TZ(NOW(), '+00:00', '+08:00')";
  private const MANILA_TODAY      = "DATE(CONVERT_TZ(NOW(), '+00:00', '+08:00'))";
  private const MANILA_CHECKED_AT = "CONVERT_TZ(cir.checked_at, '+00:00', '+08:00')";

  // -------------------------------------------------------------------------
  // Due schedules
  // -------------------------------------------------------------------------

  public static function dailySchedule(): string
  {
    $now   = self::MANILA_NOW;
    $today = self::MANILA_TODAY;
    $ca    = self::MANILA_CHECKED_AT;

    return "
        (
            s.recurrence_type = 'daily'
            AND s.interval_unit = 'day'
            AND s.day_times IS NOT NULL
            AND EXISTS (
                SELECT 1
                FROM JSON_TABLE(
                    s.day_times,
                    '\$[*]' COLUMNS (t TIME PATH '\$')
                ) jt
                WHERE
                    TIMESTAMP({$today}, jt.t) <= {$now}
                    AND (cir.checked_at IS NULL OR {$ca} < TIMESTAMP({$today}, jt.t))
            )
        )";
  }

  public static function intervalHour(): string
  {
    return "
        (
            s.recurrence_type = 'interval' AND s.interval_unit = 'hour'
            AND (cir.checked_at IS NULL OR TIMESTAMPDIFF(HOUR, cir.checked_at, NOW()) >= s.interval_value)
        )";
  }

  public static function intervalMonth(): string
  {
    $today = self::MANILA_TODAY;
    $ca    = self::MANILA_CHECKED_AT;

    return "
        (
            s.recurrence_type = 'interval' AND s.interval_unit = 'month'
            AND (cir.checked_at IS NULL OR PERIOD_DIFF(
                EXTRACT(YEAR_MONTH FROM {$today}),
                EXTRACT(YEAR_MONTH FROM {$ca})
            ) >= s.interval_value)
        )";
  }

  public static function intervalWeek(): string
  {
    $today = self::MANILA_TODAY;
    $ca    = self::MANILA_CHECKED_AT;

    return "
        (
            s.recurrence_type = 'interval' AND s.interval_unit = 'week'
            AND (cir.checked_at IS NULL OR FLOOR(DATEDIFF({$today}, DATE({$ca}))/7) >= s.interval_value)
        )";
  }

  public static function intervalDay(): string
  {
    $today = self::MANILA_TODAY;
    $ca    = self::MANILA_CHECKED_AT;

    return "
        (
            s.recurrence_type = 'interval' AND s.interval_unit = 'day'
            AND (cir.checked_at IS NULL OR DATEDIFF({$today}, DATE({$ca})) >= s.interval_value)
        )";
  }

  // -------------------------------------------------------------------------
  // Overdue schedules
  // -------------------------------------------------------------------------

  public static function overdueDailySchedule(): string
  {
    $now   = self::MANILA_NOW;
    $today = self::MANILA_TODAY;
    $ca    = self::MANILA_CHECKED_AT;

    return "
        (
            s.recurrence_type = 'daily'
            AND s.interval_unit = 'day'
            AND s.day_times IS NOT NULL
            AND EXISTS (
                SELECT 1
                FROM JSON_TABLE(
                    s.day_times,
                    '\$[*]' COLUMNS (t TIME PATH '\$')
                ) jt
                WHERE
                    TIMESTAMP({$today}, jt.t) < {$now}
                    AND (cir.checked_at IS NULL OR {$ca} < TIMESTAMP({$today}, jt.t))
            )
        )";
  }

  public static function overdueIntervalHour(): string
  {
    return "
        (
            s.recurrence_type = 'interval' AND s.interval_unit = 'hour'
            AND (cir.checked_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, cir.checked_at, NOW()) > s.interval_value)
        )";
  }

  public static function overdueIntervalMonth(): string
  {
    $today = self::MANILA_TODAY;
    $ca    = self::MANILA_CHECKED_AT;

    return "
        (
            s.recurrence_type = 'interval' AND s.interval_unit = 'month'
            AND (cir.checked_at IS NOT NULL AND PERIOD_DIFF(
                EXTRACT(YEAR_MONTH FROM {$today}),
                EXTRACT(YEAR_MONTH FROM {$ca})
            ) > s.interval_value)
        )";
  }

  public static function overdueIntervalWeek(): string
  {
    $today = self::MANILA_TODAY;
    $ca    = self::MANILA_CHECKED_AT;

    return "
        (
            s.recurrence_type = 'interval' AND s.interval_unit = 'week'
            AND (cir.checked_at IS NOT NULL AND FLOOR(DATEDIFF({$today}, DATE({$ca}))/7) > s.interval_value)
        )";
  }

  public static function overdueIntervalDay(): string
  {
    $today = self::MANILA_TODAY;
    $ca    = self::MANILA_CHECKED_AT;

    return "
        (
            s.recurrence_type = 'interval' AND s.interval_unit = 'day'
            AND (cir.checked_at IS NOT NULL AND DATEDIFF({$today}, DATE({$ca})) > s.interval_value)
        )";
  }

  // -------------------------------------------------------------------------
  // Composite expressions
  // -------------------------------------------------------------------------

  public static function dueRaw(string $alias = 'is_due'): \Illuminate\Database\Query\Expression
  {
    return DB::raw("
            CASE
                WHEN " . self::intervalDay() . " THEN 1
                WHEN " . self::intervalWeek() . " THEN 1
                WHEN " . self::intervalMonth() . " THEN 1
                WHEN " . self::intervalHour() . " THEN 1
                WHEN " . self::dailySchedule() . " THEN 1
                ELSE 0
            END as {$alias}
        ");
  }

  public static function overdueRaw(string $alias = 'is_overdue'): \Illuminate\Database\Query\Expression
  {
    return DB::raw("
            CASE
                WHEN " . self::overdueIntervalDay() . " THEN 1
                WHEN " . self::overdueIntervalWeek() . " THEN 1
                WHEN " . self::overdueIntervalMonth() . " THEN 1
                WHEN " . self::overdueIntervalHour() . " THEN 1
                WHEN " . self::overdueDailySchedule() . " THEN 1
                ELSE 0
            END as {$alias}
        ");
  }

  public static function dueCondition(): string
  {
    return sprintf(
      '(%s OR %s OR %s OR %s OR %s)',
      self::intervalDay(),
      self::intervalWeek(),
      self::intervalMonth(),
      self::intervalHour(),
      self::dailySchedule()
    );
  }

  public static function overdueCondition(): string
  {
    return sprintf(
      '(%s OR %s OR %s OR %s OR %s)',
      self::overdueIntervalDay(),
      self::overdueIntervalWeek(),
      self::overdueIntervalMonth(),
      self::overdueIntervalHour(),
      self::overdueDailySchedule()
    );
  }
}
