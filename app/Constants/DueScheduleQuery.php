<?php

namespace App\Constants;

class DueScheduleQuery
{
  public const dailySchedule = "
  (
    s.recurrence_type = 'daily'
    AND s.interval_unit = 'day'
    AND s.day_times IS NOT NULL
    AND EXISTS (
        SELECT 1
        FROM JSON_TABLE(
            s.day_times,
            '$[*]' COLUMNS (
                t TIME PATH '$'
            )
        ) jt
        WHERE
            TIMESTAMP(CURDATE(), jt.t) <= NOW()
            AND (cir.checked_at IS NULL OR cir.checked_at < TIMESTAMP(CURDATE(), jt.t))
    )
  )";

  public const intervalHour = "
  (
    s.recurrence_type = 'interval' AND s.interval_unit = 'hour'
    AND (cir.checked_at IS NULL OR TIMESTAMPDIFF(HOUR, cir.checked_at, NOW()) >= s.interval_value)
  )";

  public const intervalMonth = "
  (
    s.recurrence_type = 'interval' AND s.interval_unit = 'month'
    AND (cir.checked_at IS NULL OR PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM CURDATE()), EXTRACT(YEAR_MONTH FROM DATE(cir.checked_at))) >= s.interval_value)
  )";

  public const intervalWeek = "
  (
    s.recurrence_type = 'interval' AND s.interval_unit = 'week'
    AND (cir.checked_at IS NULL OR FLOOR(DATEDIFF(CURDATE(), DATE(cir.checked_at))/7) >= s.interval_value)
  )";

  public const intervalDay = "
  (
    s.recurrence_type = 'interval' AND s.interval_unit = 'day' 
    AND (cir.checked_at IS NULL OR DATEDIFF(CURDATE(), DATE(cir.checked_at)) >= s.interval_value)
  )";
}
