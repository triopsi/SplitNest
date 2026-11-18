<?php

namespace Tests\Feature;

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Widgets\MonthlyBudgets;
use App\Filament\Widgets\MonthlyParticipantsTable;
use App\Filament\Widgets\MonthlyRemainingStat;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\HouseholdBackup;
use App\Services\SettlementCalculator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Savings move existing income into persistent pools without duplicating cash or reducing the remainder twice. */
class SavingsContributionsTest extends TestCase
{
    use RefreshDatabase;

    /** Create two salaried participants for shared or personal saving plans.
     * @return array{Participant, Participant}
     */
    private function household(): array
    {
        $first = Participant::create(['name' => 'Alex']);
        $second = Participant::create(['name' => 'Sam']);
        foreach ([$first, $second] as $person) {
            Income::create(['name' => 'Salary', 'participant_id' => $person->id, 'amount_cents' => 100000, 'start_month' => '2027-01', 'interval_months' => 1]);
        }

        return [$first, $second];
    }

    /** Create a personal savings contribution or expense with optional shared allocation.
     * @param  array<string, mixed>  $attributes
     */
    private function expense(Participant $person, array $attributes = []): Expense
    {
        return Expense::create(['name' => 'Home savings', 'payer_id' => $person->id, 'amount_cents' => 20000, 'split_mode' => 'personal', 'shares' => [['participant_id' => $person->id]], 'start_month' => '2027-01', 'interval_months' => 1, ...$attributes]);
    }

    /** Personal savings reduce the owner's available money exactly once and carry forward independently of salary. */
    public function test_personal_savings_carry_forward_without_creating_income(): void
    {
        [$first, $second] = $this->household();
        $pool = Budget::factory()->create(['name' => 'Bauspartopf']);
        $this->expense($first, ['savings_budget_id' => $pool->id]);

        $january = app(SettlementCalculator::class)->calculate('2027-01');
        $february = app(SettlementCalculator::class)->calculate('2027-02');

        $this->assertSame(200000, $january['total_income_cents']);
        $this->assertSame([80000, 100000], array_column($january['participants'], 'remaining_cents'));
        $this->assertSame(180000, $january['remaining_cents']);
        $this->assertSame([], $january['transfers']);
        $this->assertSame(20000, $january['budgets'][0]['closing_cents']);
        $this->assertSame(20000, $february['budgets'][0]['opening_cents']);
        $this->assertSame(40000, $february['budgets'][0]['closing_cents']);
        $this->assertSame([80000, 100000], array_column($february['participants'], 'remaining_cents'));
    }

    /** Shared saving plans collect both shares with the holder and balance them through the existing transfers. */
    public function test_shared_savings_split_the_burden_and_credit_the_pool_holder(): void
    {
        [$first, $second] = $this->household();
        $pool = Budget::factory()->create();
        $this->expense($first, ['savings_budget_id' => $pool->id, 'split_mode' => 'equal', 'shares' => [['participant_id' => $first->id], ['participant_id' => $second->id]]]);
        $this->expense($second, ['name' => 'Rent', 'amount_cents' => 40000, 'split_mode' => 'equal', 'shares' => [['participant_id' => $first->id], ['participant_id' => $second->id]]]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame([70000, 70000], array_column($report['participants'], 'remaining_cents'));
        $this->assertSame([$first->id => 20000], $report['budgets'][0]['balances']);
        $this->assertSame($first->id, $report['transfers'][0]['from_id']);
        $this->assertSame($second->id, $report['transfers'][0]['to_id']);
        $this->assertSame(10000, $report['transfers'][0]['amount_cents']);
        $this->assertSame('Home savings', $report['expenses'][0]['name']);
        $this->assertSame($pool->name, $report['expenses'][0]['savings_budget_name']);
    }

    /** Savings fund ordinary bills in the same month regardless of entry order and only uncovered costs reduce free income. */
    public function test_savings_are_available_to_assigned_expenses_in_the_same_month(): void
    {
        [$first, $second] = $this->household();
        $pool = Budget::factory()->create();
        $this->expense($second, ['name' => 'Groceries', 'budget_id' => $pool->id, 'amount_cents' => 25000]);
        $this->expense($first, ['savings_budget_id' => $pool->id]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame(20000, $report['expenses'][0]['budget_cents']);
        $this->assertSame(5000, $report['expenses'][0]['uncovered_cents']);
        $this->assertSame(0, $report['budgets'][0]['closing_cents']);
        $this->assertSame([80000, 95000], array_column($report['participants'], 'remaining_cents'));
        $this->assertSame(20000, $report['transfers'][0]['amount_cents']);
    }

    /** One-off savings before salaries start replay across years and can pay a later bill without reducing that month's salary. */
    public function test_prior_savings_cover_future_expenses_without_being_deducted_again(): void
    {
        [$first, $second] = $this->household();
        $pool = Budget::factory()->create();
        $otherPool = Budget::factory()->create();
        $this->expense($first, ['savings_budget_id' => $pool->id, 'start_month' => '2026-12', 'interval_months' => 0]);
        $this->expense($second, ['savings_budget_id' => $otherPool->id, 'amount_cents' => 3000, 'start_month' => '2026-12', 'interval_months' => 0]);
        $this->expense($second, ['name' => 'Later bill', 'budget_id' => $pool->id, 'amount_cents' => 5000, 'interval_months' => 0]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame(20000, $report['budgets'][0]['opening_cents']);
        $this->assertSame(15000, $report['budgets'][0]['closing_cents']);
        $this->assertSame(3000, $report['budgets'][1]['closing_cents']);
        $this->assertSame([100000, 100000], array_column($report['participants'], 'remaining_cents'));
    }

    /** Closed savings stay frozen when live amounts change, while following months use their own effective price. */
    public function test_closed_savings_and_future_price_changes_preserve_their_monthly_balances(): void
    {
        [$first, $second] = $this->household();
        $pool = Budget::factory()->create();
        $saving = $this->expense($first, ['savings_budget_id' => $pool->id, 'end_month' => '2027-02', 'price_changes' => [['month' => '2027-02', 'amount' => '300']]]);
        $closing = app(CloseMonth::class)->close('2027-01', User::factory()->create(['is_admin' => true])->id);
        $saving->update(['amount_cents' => 90000]);

        $report = app(SettlementCalculator::class)->calculate('2027-03');

        $this->assertSame(20000, $closing->fresh()->snapshot['budgets'][0]['closing_cents']);
        $this->assertSame(80000, $closing->snapshot['participants'][0]['remaining_cents']);
        $this->assertSame(50000, $report['budgets'][0]['closing_cents']);
        $this->assertSame([100000, 100000], array_column($report['participants'], 'remaining_cents'));
    }

    /** Saving more than available income keeps the planned reserve and exposes the monthly shortfall. */
    public function test_savings_can_expose_a_negative_monthly_remainder(): void
    {
        [$first] = $this->household();
        $this->expense($first, ['savings_budget_id' => Budget::factory()->create()->id, 'amount_cents' => 120000]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame(-20000, $report['participants'][0]['remaining_cents']);
        $this->assertSame(120000, $report['budgets'][0]['closing_cents']);
    }

    /** A contribution cannot draw money from another pool while also creating a reserve. */
    public function test_savings_cannot_also_use_a_source_budget(): void
    {
        [$first] = $this->household();
        $pool = Budget::factory()->create();

        $this->expectException(ValidationException::class);
        $this->expense($first, ['budget_id' => $pool->id, 'savings_budget_id' => $pool->id]);
    }

    /** Savings cannot receive reimbursements that would incorrectly introduce extra earmarked cash. */
    public function test_savings_cannot_receive_reimbursements(): void
    {
        [$first] = $this->household();
        $saving = $this->expense($first, ['savings_budget_id' => Budget::factory()->create()->id]);

        $this->expectException(ValidationException::class);
        Income::create(['name' => 'Refund', 'participant_id' => $first->id, 'expense_id' => $saving->id, 'amount_cents' => 100, 'start_month' => '2027-01', 'interval_months' => 1]);
    }

    /** Existing reimbursements must be removed before converting their bill to a savings contribution. */
    public function test_reimbursed_bills_cannot_be_converted_to_savings(): void
    {
        [$first] = $this->household();
        $bill = $this->expense($first);
        Income::create(['name' => 'Refund', 'participant_id' => $first->id, 'expense_id' => $bill->id, 'amount_cents' => 100, 'start_month' => '2027-01', 'interval_months' => 1]);

        $this->expectException(ValidationException::class);
        $bill->update(['savings_budget_id' => Budget::factory()->create()->id]);
    }

    /** The expense form persists savings destinations and the dashboard shows each person's remainder and pool balance. */
    public function test_savings_form_and_monthly_widgets_show_the_planned_amounts(): void
    {
        [$first, $second] = $this->household();
        $pool = Budget::factory()->create(['name' => 'Bauspartopf']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Livewire::test(ManageExpenses::class)->mountAction('create')->fillForm([
            'name' => 'Monthly savings', 'payer_id' => $first->id, 'amount' => '200',
            'savings_budget_id' => $pool->id, 'start_month' => '2027-01', 'interval_months' => 1,
            'split_mode' => 'personal', 'shares' => [['participant_id' => $first->id]],
        ])->callMountedAction()->assertHasNoFormErrors();

        $this->assertDatabaseHas('expenses', ['savings_budget_id' => $pool->id, 'budget_id' => null, 'amount_cents' => 20000]);
        Livewire::test(MonthlyRemainingStat::class, ['month' => '2027-01'])->assertSee('Alex: 800,00 €')->assertSee('Sam: 1.000,00 €');
        Livewire::test(MonthlyParticipantsTable::class, ['month' => '2027-01'])->assertSee(__('settlement.overview.left'))->assertSee('800,00 €');
        Livewire::test(MonthlyBudgets::class, ['month' => '2027-01'])->assertSee('Bauspartopf')->assertSee('200,00 €');
    }

    /** Complete backups preserve savings destinations and their calculated carryovers after restoration. */
    public function test_backup_round_trip_preserves_savings(): void
    {
        [$first] = $this->household();
        $this->expense($first, ['savings_budget_id' => Budget::factory()->create()->id]);
        $expected = app(SettlementCalculator::class)->calculate('2027-02');
        $path = tempnam(sys_get_temp_dir(), 'savings-backup-');
        try {
            app(HouseholdBackup::class)->exportToZip($path);
            Expense::query()->delete();
            app(HouseholdBackup::class)->importFromZip($path);
            $this->assertSame($expected, app(SettlementCalculator::class)->calculate('2027-02'));
        } finally {
            unlink($path);
        }
    }
}
