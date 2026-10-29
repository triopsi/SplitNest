<?php

namespace Tests\Feature;

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\SettlementCalculator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Effective prices follow calendar boundaries while preserving billing schedules and frozen closings. */
class ExpensePriceChangesTest extends TestCase
{
    use RefreshDatabase;

    /** Create a personal monthly bill with explicit timeline overrides.
     * @param  array<string, mixed>  $attributes
     */
    private function bill(Participant $person, array $attributes = []): Expense
    {
        return Expense::create(['name' => 'Internet', 'payer_id' => $person->id, 'amount_cents' => 4000, 'split_mode' => 'personal', 'shares' => [['participant_id' => $person->id]], 'start_month' => '2027-01', 'interval_months' => 1, ...$attributes]);
    }

    /** Unsorted changes, price decreases and free periods resolve to the latest effective amount. */
    public function test_price_timeline_preserves_the_base_and_earlier_months(): void
    {
        $person = Participant::create(['name' => 'Payer']);
        $bill = $this->bill($person, ['price_changes' => [['month' => '2027-05', 'amount' => '30,00'], ['month' => '2027-02', 'amount' => '45.50'], ['month' => '2027-03', 'amount' => '0']]]);

        $this->assertSame(4000, $bill->amountForMonth('2027-01'));
        $this->assertSame(4550, $bill->amountForMonth('2027-02'));
        $this->assertSame(0, $bill->amountForMonth('2027-04'));
        $this->assertSame(3000, $bill->amountForMonth('2027-05'));
        $this->assertSame(3000, app(SettlementCalculator::class)->calculate('2027-06')['total_expense_cents']);
        $this->assertSame(4000, $bill->fresh()->amount_cents);
    }

    /** An effective month between due dates changes the next price without moving quarterly occurrences. */
    public function test_price_changes_do_not_restart_the_billing_interval(): void
    {
        $person = Participant::create(['name' => 'Payer']);
        $this->bill($person, ['interval_months' => 3, 'price_changes' => [['month' => '2027-02', 'amount' => '50']]]);

        $this->assertSame(4000, app(SettlementCalculator::class)->calculate('2027-01')['total_expense_cents']);
        $this->assertSame(0, app(SettlementCalculator::class)->calculate('2027-03')['total_expense_cents']);
        $this->assertSame(5000, app(SettlementCalculator::class)->calculate('2027-04')['total_expense_cents']);
    }

    /** Budget projections use the price effective in each replayed month instead of today's price. */
    public function test_budget_carryovers_replay_each_months_own_price(): void
    {
        $person = Participant::create(['name' => 'Recipient']);
        $budget = Budget::factory()->create();
        Income::create(['name' => 'Funding', 'participant_id' => $person->id, 'budget_id' => $budget->id, 'amount_cents' => 6000, 'start_month' => '2027-01', 'interval_months' => 1]);
        $this->bill($person, ['budget_id' => $budget->id, 'price_changes' => [['month' => '2027-02', 'amount' => '50']]]);

        $report = app(SettlementCalculator::class)->calculate('2027-03');

        $this->assertSame(3000, $report['budgets'][0]['opening_cents']);
        $this->assertSame(5000, $report['budgets'][0]['spent_cents']);
        $this->assertSame(4000, $report['budgets'][0]['closing_cents']);
        $this->assertSame([], $report['transfers']);
    }

    /** A price change after the only occurrence would never be billed and must be rejected. */
    public function test_one_off_expenses_reject_price_changes_after_their_only_occurrence(): void
    {
        $person = Participant::create(['name' => 'Payer']);

        $this->expectException(ValidationException::class);
        $this->bill($person, ['interval_months' => 0, 'price_changes' => [['month' => '2027-02', 'amount' => '50']]]);
    }

    /** A reimbursement cannot exceed a bill after its scheduled price decreases. */
    public function test_reimbursement_limit_uses_the_effective_price(): void
    {
        $person = Participant::create(['name' => 'Recipient']);
        $bill = $this->bill($person, ['price_changes' => [['month' => '2027-02', 'amount' => '20']]]);
        Income::create(['name' => 'Contribution', 'participant_id' => $person->id, 'expense_id' => $bill->id, 'amount_cents' => 2500, 'start_month' => '2027-01', 'interval_months' => 1]);
        $this->assertSame(1500, app(SettlementCalculator::class)->calculate('2027-01')['expenses'][0]['uncovered_cents']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('funding.refund_exceeds_cost', ['name' => 'Internet']));
        app(SettlementCalculator::class)->calculate('2027-02');
    }

    /** Timeline edits cannot mutate an already closed month's expense amount. */
    public function test_closed_months_keep_their_saved_price(): void
    {
        $person = Participant::create(['name' => 'Payer']);
        $bill = $this->bill($person);
        $closing = app(CloseMonth::class)->close('2027-01', User::factory()->create(['is_admin' => true])->id);

        $bill->update(['price_changes' => [['month' => '2027-01', 'amount' => '90']]]);

        $this->assertSame(4000, $closing->fresh()->snapshot['expenses'][0]['amount_cents']);
        $this->assertSame(9000, app(SettlementCalculator::class)->calculate('2027-02')['total_expense_cents']);
    }

    /** Invalid timeline entries are rejected before any expense is persisted. */
    #[DataProvider('invalidChanges')]
    public function test_invalid_price_changes_are_rejected(array $changes): void
    {
        $person = Participant::create(['name' => 'Payer']);
        try {
            $this->bill($person, ['end_month' => '2027-12', 'price_changes' => $changes]);
            $this->fail('Invalid price changes accepted');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertDatabaseCount('expenses', 0);
    }

    /** Each case covers a different timeline validation boundary.
     * @return array<string, array{array}>
     */
    public static function invalidChanges(): array
    {
        return [
            'duplicate months' => [[['month' => '2027-02', 'amount' => '45'], ['month' => '2027-02', 'amount' => '50']]],
            'before start' => [[['month' => '2026-12', 'amount' => '45']]],
            'after end' => [[['month' => '2028-01', 'amount' => '45']]],
            'invalid month' => [[['month' => '2027-13', 'amount' => '45']]],
            'negative amount' => [[['month' => '2027-02', 'amount' => '-1']]],
            'fractional cent' => [[['month' => '2027-02', 'amount' => '1.001']]],
            'unknown field' => [[['month' => '2027-02', 'amount' => '45', 'unexpected' => true]]],
        ];
    }

    /** The expense editor saves and reloads multiple prices on the original cost item. */
    public function test_price_changes_are_editable_in_the_expense_form(): void
    {
        $person = Participant::create(['name' => 'Payer']);
        $bill = $this->bill($person);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageExpenses::class)->callTableAction('edit', $bill, data: ['price_changes' => [['month' => '2027-02', 'amount' => '45,50']]])->assertHasNoTableActionErrors();

        $this->assertSame(4550, $bill->fresh()->amountForMonth('2027-02'));
        $this->assertSame(1, Expense::count());
        $this->assertSame('2027-01', $bill->fresh()->start_month);
    }
}
