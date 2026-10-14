<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Schedule and amount validation for expenses: guards the data integrity
 * of the billing rules behind every settlement.
 */
class ScheduleValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an expense with sensible defaults, overridable per test.
     */
    private function makeExpense(array $extra = []): Expense
    {
        $p = Participant::firstOrCreate(['name' => 'Daniel']);

        return Expense::create(array_replace([
            'name' => 'Test', 'payer_id' => $p->id, 'amount_cents' => 1000,
            'split_mode' => 'personal', 'shares' => [['participant_id' => $p->id]],
            'start_month' => '2027-01', 'interval_months' => 1,
        ], $extra));
    }

    /**
     * Write and read an expense amount through the decimal accessor.
     *
     * Expects '10,50' to be stored as 1050 cents and read back as '10.50'.
     */
    #[Group('critical')]
    public function test_amount_accessor_maps_decimal_to_cents_and_back(): void
    {
        $e = $this->makeExpense(['amount' => '10,50']);
        $this->assertSame(1050, $e->amount_cents);
        $this->assertSame('10.50', $e->amount);
    }

    /**
     * Check the applicability of a one-off entry (interval 0).
     *
     * Expects it to apply only to its start month, not to the months around it.
     */
    public function test_one_off_entry_applies_only_to_its_start_month(): void
    {
        $e = $this->makeExpense(['interval_months' => 0]);
        $this->assertTrue($e->appliesTo('2027-01'));
        $this->assertFalse($e->appliesTo('2026-12'));
        $this->assertFalse($e->appliesTo('2027-02'));
    }

    /**
     * Check yearly and quarterly intervals against due and non-due months.
     *
     * Expects hits exactly on the interval rhythm (e.g. every 12/3 months).
     */
    public function test_yearly_and_quarterly_intervals(): void
    {
        $e = $this->makeExpense(['interval_months' => 12]);
        $this->assertTrue($e->appliesTo('2028-01'));
        $this->assertFalse($e->appliesTo('2027-07'));

        $q = $this->makeExpense(['interval_months' => 3, 'name' => 'Quartal']);
        $this->assertTrue($q->appliesTo('2027-04'));
        $this->assertFalse($q->appliesTo('2027-05'));
    }

    /**
     * Check the boundary month of an entry with an end month.
     *
     * Expects the end month to be inclusive and the month after to be out.
     */
    public function test_end_month_is_inclusive(): void
    {
        $e = $this->makeExpense(['end_month' => '2027-03']);
        $this->assertTrue($e->appliesTo('2027-03'));
        $this->assertFalse($e->appliesTo('2027-04'));
    }

    /**
     * Create an entry with an unsupported interval (5 months).
     *
     * Expects a ValidationException; only 0, 1, 2, 3, 4, 6 and 12 are allowed.
     */
    public function test_invalid_interval_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeExpense(['interval_months' => 5]);
    }

    /**
     * Create an entry whose end month lies before its start month.
     *
     * Expects a ValidationException.
     */
    public function test_end_month_before_start_month_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeExpense(['end_month' => '2026-12']);
    }

    /**
     * Create an entry with a negative amount.
     *
     * Expects a ValidationException; negative costs would break the settlement.
     */
    #[Group('critical')]
    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeExpense(['amount_cents' => -100]);
    }

    /**
     * Create an entry with a malformed start month ('01/2027').
     *
     * Expects a ValidationException; months must follow the Y-m format.
     */
    public function test_invalid_start_month_format_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeExpense(['start_month' => '01/2027']);
    }

    /**
     * Create a personal split that names two participants.
     *
     * Expects a ValidationException; personal costs must belong to exactly one person.
     */
    #[Group('critical')]
    public function test_personal_split_requires_exactly_one_participant(): void
    {
        $b = Participant::create(['name' => 'Svenja']);
        $p = Participant::firstOrCreate(['name' => 'Daniel']);
        $this->expectException(ValidationException::class);
        $this->makeExpense(['shares' => [['participant_id' => $p->id], ['participant_id' => $b->id]]]);
    }

    /**
     * Create a percentage split with a single share above 100 %.
     *
     * Expects a ValidationException.
     */
    public function test_single_share_may_not_exceed_100_percent(): void
    {
        $p = Participant::firstOrCreate(['name' => 'Daniel']);
        $this->expectException(ValidationException::class);
        $this->makeExpense(['split_mode' => 'percentage', 'shares' => [['participant_id' => $p->id, 'percentage' => '100,01']]]);
    }

    /**
     * Create a percentage split whose shares only sum to 90 %.
     *
     * Expects a ValidationException; shares must total exactly 100 % so the
     * full amount is allocated.
     */
    #[Group('critical')]
    public function test_percentage_shares_must_sum_to_exactly_100(): void
    {
        $p = Participant::firstOrCreate(['name' => 'Daniel']);
        $b = Participant::create(['name' => 'Svenja']);
        $this->expectException(ValidationException::class);
        $this->makeExpense(['split_mode' => 'percentage', 'shares' => [['participant_id' => $p->id, 'percentage' => '50'], ['participant_id' => $b->id, 'percentage' => '40']]]);
    }
}
