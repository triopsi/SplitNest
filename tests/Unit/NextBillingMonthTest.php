<?php

namespace Tests\Unit;

use App\Models\Expense;
use Tests\TestCase;

class NextBillingMonthTest extends TestCase
{
    /**
     * Build an unsaved expense with the given schedule.
     */
    private function expense(string $start, int $interval, ?string $end = null): Expense
    {
        return new Expense(['name' => 'Test', 'start_month' => $start, 'end_month' => $end, 'interval_months' => $interval]);
    }

    /**
     * Ask a monthly expense for its next billing month.
     *
     * Expects the current month itself, because monthly entries are always due.
     */
    public function test_monthly_entry_is_due_every_month(): void
    {
        $e = $this->expense('2026-01', 1);
        $this->assertSame('2026-10', $e->nextBillingMonth('2026-10'));
    }

    /**
     * Ask a quarterly expense for the months after a quarter month.
     *
     * Expects the same month when due, otherwise the next quarter month.
     */
    public function test_quarterly_entry_rolls_to_the_next_quarter(): void
    {
        $e = $this->expense('2026-01', 3);
        $this->assertSame('2026-10', $e->nextBillingMonth('2026-10'));
        $this->assertSame('2027-01', $e->nextBillingMonth('2026-11'));
    }

    /**
     * Ask about an expense whose start month lies in the future.
     *
     * Expects the start month as the first billing month.
     */
    public function test_entry_starting_in_the_future_reports_its_start_month(): void
    {
        $e = $this->expense('2027-03', 1);
        $this->assertSame('2027-03', $e->nextBillingMonth('2026-10'));
    }

    /**
     * Ask a one-off expense (interval 0) before and after its month.
     *
     * Expects its own month beforehand and null once it has passed.
     */
    public function test_one_off_entry_is_null_after_its_month(): void
    {
        $e = $this->expense('2026-01', 0);
        $this->assertSame('2026-01', $e->nextBillingMonth('2025-12'));
        $this->assertNull($e->nextBillingMonth('2026-02'));
    }

    /**
     * Ask an expense with an end month at and after its end.
     *
     * Expects the end month to be inclusive and null afterwards.
     */
    public function test_ended_entry_reports_null(): void
    {
        $e = $this->expense('2026-01', 1, '2026-06');
        $this->assertSame('2026-06', $e->nextBillingMonth('2026-06'));
        $this->assertNull($e->nextBillingMonth('2026-10'));
    }

    /**
     * Ask a half-yearly expense for a month whose next billing month would
     * lie beyond its end month.
     *
     * Expects null, because no further due date exists within the period.
     */
    public function test_next_billing_beyond_the_end_month_is_null(): void
    {
        $e = $this->expense('2026-01', 6, '2026-06');
        $this->assertNull($e->nextBillingMonth('2026-07'));
    }
}
