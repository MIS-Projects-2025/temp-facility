<?php

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use App\Services\OverdueCalculatorService;

class DerivePeriodTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function schedule(array $overrides): array
    {
        return array_merge([
            'recurrence_type'  => 'interval',
            'interval_unit'    => null,
            'interval_value'   => null,
            'days_of_week'     => null,
            'days_of_month'    => null,
            'nth_weekday'      => null,
            'weekday_of_month' => null,
            'months'           => null,
            'day_times'        => null,
        ], $overrides);
    }

    private function assertPeriod(
        string $expectedStart,
        string $expectedEnd,
        array $result
    ): void {
        [$start, $end] = $result;
        $this->assertEquals(
            $expectedStart,
            $start->toDateTimeString(),
            "period_start mismatch"
        );
        $this->assertEquals(
            $expectedEnd,
            $end->toDateTimeString(),
            "period_end mismatch"
        );
    }

    // -------------------------------------------------------------------------
    // Daily
    // -------------------------------------------------------------------------

    public function test_daily_start_of_day(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 00:00:00'),
            $this->schedule(['interval_unit' => 'day', 'interval_value' => 1])
        );
        $this->assertPeriod('2026-03-17 00:00:00', '2026-03-17 23:59:59', $result);
    }

    public function test_daily_mid_day(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 14:30:00'),
            $this->schedule(['interval_unit' => 'day', 'interval_value' => 1])
        );
        $this->assertPeriod('2026-03-17 00:00:00', '2026-03-17 23:59:59', $result);
    }

    public function test_daily_end_of_day(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 23:59:59'),
            $this->schedule(['interval_unit' => 'day', 'interval_value' => 1])
        );
        $this->assertPeriod('2026-03-17 00:00:00', '2026-03-17 23:59:59', $result);
    }

    // -------------------------------------------------------------------------
    // Twice a Day (AM / PM)
    // -------------------------------------------------------------------------

    public function test_twice_a_day_am_window(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 09:34:45'),
            $this->schedule([
                'recurrence_type' => 'daily',
                'interval_unit'   => 'day',
                'interval_value'  => 1,
                'day_times'       => ['00:00', '12:00'],
            ])
        );
        $this->assertPeriod('2026-03-17 00:00:00', '2026-03-17 11:59:59', $result);
    }

    public function test_twice_a_day_pm_window(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 15:00:00'),
            $this->schedule([
                'recurrence_type' => 'daily',
                'interval_unit'   => 'day',
                'interval_value'  => 1,
                'day_times'       => ['00:00', '12:00'],
            ])
        );
        $this->assertPeriod('2026-03-17 12:00:00', '2026-03-17 23:59:59', $result);
    }

    public function test_twice_a_day_exactly_at_pm_boundary(): void
    {
        // 12:00:00 exactly should belong to PM, not AM
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 12:00:00'),
            $this->schedule([
                'recurrence_type' => 'daily',
                'interval_unit'   => 'day',
                'interval_value'  => 1,
                'day_times'       => ['00:00', '12:00'],
            ])
        );
        $this->assertPeriod('2026-03-17 12:00:00', '2026-03-17 23:59:59', $result);
    }

    public function test_twice_a_day_one_second_before_pm_boundary(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 11:59:59'),
            $this->schedule([
                'recurrence_type' => 'daily',
                'interval_unit'   => 'day',
                'interval_value'  => 1,
                'day_times'       => ['00:00', '12:00'],
            ])
        );
        $this->assertPeriod('2026-03-17 00:00:00', '2026-03-17 11:59:59', $result);
    }

    // -------------------------------------------------------------------------
    // Weekly (generic Mon–Sun)
    // -------------------------------------------------------------------------

    public function test_weekly_generic_mid_week(): void
    {
        // March 17 2026 is a Tuesday
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 10:00:00'),
            $this->schedule(['interval_unit' => 'week', 'interval_value' => 1])
        );
        $this->assertPeriod('2026-03-16 00:00:00', '2026-03-22 23:59:59', $result);
    }

    public function test_weekly_generic_on_monday(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-16 00:00:00'),
            $this->schedule(['interval_unit' => 'week', 'interval_value' => 1])
        );
        $this->assertPeriod('2026-03-16 00:00:00', '2026-03-22 23:59:59', $result);
    }

    public function test_weekly_generic_on_sunday(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-22 23:59:59'),
            $this->schedule(['interval_unit' => 'week', 'interval_value' => 1])
        );
        $this->assertPeriod('2026-03-16 00:00:00', '2026-03-22 23:59:59', $result);
    }

    // -------------------------------------------------------------------------
    // Weekly (specific day — Saturday = 6)
    // -------------------------------------------------------------------------

    public function test_weekly_saturday_on_saturday(): void
    {
        // March 21 2026 is a Saturday
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-21 08:00:00'),
            $this->schedule([
                'interval_unit'  => 'week',
                'interval_value' => 1,
                'days_of_week'   => [6],
            ])
        );
        $this->assertPeriod('2026-03-21 00:00:00', '2026-03-21 23:59:59', $result);
    }

    public function test_weekly_saturday_checked_on_tuesday_resolves_previous_saturday(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 10:00:00'), // Tuesday
            $this->schedule([
                'interval_unit'  => 'week',
                'interval_value' => 1,
                'days_of_week'   => [6],
            ])
        );
        $this->assertPeriod('2026-03-14 00:00:00', '2026-03-14 23:59:59', $result);
    }

    // -------------------------------------------------------------------------
    // Monthly (generic)
    // -------------------------------------------------------------------------

    public function test_monthly_generic_mid_month(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 10:00:00'),
            $this->schedule(['interval_unit' => 'month', 'interval_value' => 1])
        );
        $this->assertPeriod('2026-03-01 00:00:00', '2026-03-31 23:59:59', $result);
    }

    public function test_monthly_generic_february_non_leap(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-02-14 10:00:00'),
            $this->schedule(['interval_unit' => 'month', 'interval_value' => 1])
        );
        $this->assertPeriod('2026-02-01 00:00:00', '2026-02-28 23:59:59', $result);
    }

    // -------------------------------------------------------------------------
    // Monthly (specific day of month)
    // -------------------------------------------------------------------------

    public function test_monthly_specific_day_14(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-14 09:00:00'),
            $this->schedule([
                'interval_unit'  => 'month',
                'interval_value' => 1,
                'days_of_month'  => [14],
            ])
        );
        $this->assertPeriod('2026-03-14 00:00:00', '2026-03-14 23:59:59', $result);
    }

    public function test_monthly_specific_day_1(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-01 00:00:00'),
            $this->schedule([
                'interval_unit'  => 'month',
                'interval_value' => 1,
                'days_of_month'  => [1],
            ])
        );
        $this->assertPeriod('2026-03-01 00:00:00', '2026-03-01 23:59:59', $result);
    }

    // -------------------------------------------------------------------------
    // First Monday of the Month (nth_weekday + weekday_of_month)
    // -------------------------------------------------------------------------

    public function test_first_monday_of_month(): void
    {
        // First Monday of March 2026 is March 2
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-02 08:00:00'),
            $this->schedule([
                'interval_unit'    => 'month',
                'interval_value'   => 1,
                'nth_weekday'      => 1,
                'weekday_of_month' => 1, // Monday
            ])
        );
        $this->assertPeriod('2026-03-02 00:00:00', '2026-03-02 23:59:59', $result);
    }

    // -------------------------------------------------------------------------
    // Quarterly
    // -------------------------------------------------------------------------

    public function test_quarterly_q1(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-02-14 10:00:00'),
            $this->schedule(['interval_unit' => 'month', 'interval_value' => 3])
        );
        $this->assertPeriod('2026-01-01 00:00:00', '2026-03-31 23:59:59', $result);
    }

    public function test_quarterly_q2(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-05-01 10:00:00'),
            $this->schedule(['interval_unit' => 'month', 'interval_value' => 3])
        );
        $this->assertPeriod('2026-04-01 00:00:00', '2026-06-30 23:59:59', $result);
    }

    public function test_quarterly_q3(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-08-31 23:59:59'),
            $this->schedule(['interval_unit' => 'month', 'interval_value' => 3])
        );
        $this->assertPeriod('2026-07-01 00:00:00', '2026-09-30 23:59:59', $result);
    }

    public function test_quarterly_q4(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-12-01 00:00:00'),
            $this->schedule(['interval_unit' => 'month', 'interval_value' => 3])
        );
        $this->assertPeriod('2026-10-01 00:00:00', '2026-12-31 23:59:59', $result);
    }

    // -------------------------------------------------------------------------
    // Semi-Annually
    // -------------------------------------------------------------------------

    public function test_semi_annually_first_half(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 10:00:00'),
            $this->schedule(['interval_unit' => 'month', 'interval_value' => 6])
        );
        $this->assertPeriod('2026-01-01 00:00:00', '2026-06-30 23:59:59', $result);
    }

    public function test_semi_annually_second_half(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-09-01 10:00:00'),
            $this->schedule(['interval_unit' => 'month', 'interval_value' => 6])
        );
        $this->assertPeriod('2026-07-01 00:00:00', '2026-12-31 23:59:59', $result);
    }

    public function test_semi_annually_boundary_july_1(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-07-01 00:00:00'),
            $this->schedule(['interval_unit' => 'month', 'interval_value' => 6])
        );
        $this->assertPeriod('2026-07-01 00:00:00', '2026-12-31 23:59:59', $result);
    }

    // -------------------------------------------------------------------------
    // Annually
    // -------------------------------------------------------------------------

    public function test_annually_mid_year(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-06-15 10:00:00'),
            $this->schedule(['interval_unit' => 'year', 'interval_value' => 1])
        );
        $this->assertPeriod('2026-01-01 00:00:00', '2026-12-31 23:59:59', $result);
    }

    public function test_annually_start_of_year(): void
    {
        $result = OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-01-01 00:00:00'),
            $this->schedule(['interval_unit' => 'year', 'interval_value' => 1])
        );
        $this->assertPeriod('2026-01-01 00:00:00', '2026-12-31 23:59:59', $result);
    }

    // -------------------------------------------------------------------------
    // Unsupported schedules throw
    // -------------------------------------------------------------------------

    public function test_multi_year_3_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 10:00:00'),
            $this->schedule(['interval_unit' => 'year', 'interval_value' => 3])
        );
    }

    public function test_multi_year_5_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 10:00:00'),
            $this->schedule(['interval_unit' => 'year', 'interval_value' => 5])
        );
    }

    public function test_hour_based_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OverdueCalculatorService::derivePeriod(
            Carbon::parse('2026-03-17 10:00:00'),
            $this->schedule(['interval_unit' => 'hour', 'interval_value' => 4000])
        );
    }
}
