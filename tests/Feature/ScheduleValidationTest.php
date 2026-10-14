<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScheduleValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpense(array $extra = []): Expense
    {
        $p = Participant::firstOrCreate(['name' => 'Daniel']);

        return Expense::create(array_replace([
            'name' => 'Test', 'payer_id' => $p->id, 'amount_cents' => 1000,
            'split_mode' => 'personal', 'shares' => [['participant_id' => $p->id]],
            'start_month' => '2027-01', 'interval_months' => 1,
        ], $extra));
    }

    public function test_amount_accessor_maps_decimal_to_cents_and_back(): void
    {
        $e = $this->makeExpense(['amount' => '10,50']);
        $this->assertSame(1050, $e->amount_cents);
        $this->assertSame('10.50', $e->amount);
    }

    public function test_one_off_entry_applies_only_to_its_start_month(): void
    {
        $e = $this->makeExpense(['interval_months' => 0]);
        $this->assertTrue($e->appliesTo('2027-01'));
        $this->assertFalse($e->appliesTo('2026-12'));
        $this->assertFalse($e->appliesTo('2027-02'));
    }

    public function test_yearly_and_quarterly_intervals(): void
    {
        $e = $this->makeExpense(['interval_months' => 12]);
        $this->assertTrue($e->appliesTo('2028-01'));
        $this->assertFalse($e->appliesTo('2027-07'));

        $q = $this->makeExpense(['interval_months' => 3, 'name' => 'Quartal']);
        $this->assertTrue($q->appliesTo('2027-04'));
        $this->assertFalse($q->appliesTo('2027-05'));
    }

    public function test_end_month_is_inclusive(): void
    {
        $e = $this->makeExpense(['end_month' => '2027-03']);
        $this->assertTrue($e->appliesTo('2027-03'));
        $this->assertFalse($e->appliesTo('2027-04'));
    }

    public function test_invalid_interval_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeExpense(['interval_months' => 5]);
    }

    public function test_end_month_before_start_month_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeExpense(['end_month' => '2026-12']);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeExpense(['amount_cents' => -100]);
    }

    public function test_invalid_start_month_format_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeExpense(['start_month' => '01/2027']);
    }

    public function test_personal_split_requires_exactly_one_participant(): void
    {
        $b = Participant::create(['name' => 'Svenja']);
        $p = Participant::firstOrCreate(['name' => 'Daniel']);
        $this->expectException(ValidationException::class);
        $this->makeExpense(['shares' => [['participant_id' => $p->id], ['participant_id' => $b->id]]]);
    }

    public function test_single_share_may_not_exceed_100_percent(): void
    {
        $p = Participant::firstOrCreate(['name' => 'Daniel']);
        $this->expectException(ValidationException::class);
        $this->makeExpense(['split_mode' => 'percentage', 'shares' => [['participant_id' => $p->id, 'percentage' => '100,01']]]);
    }

    public function test_percentage_shares_must_sum_to_exactly_100(): void
    {
        $p = Participant::firstOrCreate(['name' => 'Daniel']);
        $b = Participant::create(['name' => 'Svenja']);
        $this->expectException(ValidationException::class);
        $this->makeExpense(['split_mode' => 'percentage', 'shares' => [['participant_id' => $p->id, 'percentage' => '50'], ['participant_id' => $b->id, 'percentage' => '40']]]);
    }
}
