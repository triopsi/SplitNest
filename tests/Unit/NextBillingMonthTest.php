<?php

namespace Tests\Unit;

use App\Models\Expense;
use Tests\TestCase;

class NextBillingMonthTest extends TestCase
{
    private function expense(string $start, int $interval, ?string $end = null): Expense
    {
        return new Expense(['name' => 'Test', 'start_month' => $start, 'end_month' => $end, 'interval_months' => $interval]);
    }

    public function test_monthly_entry_is_due_every_month(): void
    {
        $e = $this->expense('2026-01', 1);
        $this->assertSame('2026-10', $e->nextBillingMonth('2026-10'));
    }

    public function test_quarterly_entry_rolls_to_the_next_quarter(): void
    {
        $e = $this->expense('2026-01', 3);
        $this->assertSame('2026-10', $e->nextBillingMonth('2026-10'));
        $this->assertSame('2027-01', $e->nextBillingMonth('2026-11'));
    }

    public function test_entry_starting_in_the_future_reports_its_start_month(): void
    {
        $e = $this->expense('2027-03', 1);
        $this->assertSame('2027-03', $e->nextBillingMonth('2026-10'));
    }

    public function test_one_off_entry_is_null_after_its_month(): void
    {
        $e = $this->expense('2026-01', 0);
        $this->assertSame('2026-01', $e->nextBillingMonth('2025-12'));
        $this->assertNull($e->nextBillingMonth('2026-02'));
    }

    public function test_ended_entry_reports_null(): void
    {
        $e = $this->expense('2026-01', 1, '2026-06');
        $this->assertSame('2026-06', $e->nextBillingMonth('2026-06'));
        $this->assertNull($e->nextBillingMonth('2026-10'));
    }

    public function test_next_billing_beyond_the_end_month_is_null(): void
    {
        $e = $this->expense('2026-01', 6, '2026-06');
        $this->assertNull($e->nextBillingMonth('2026-07'));
    }
}
