<?php

namespace Tests\Feature;

use App\Filament\Pages\ParticipantMonth;
use App\Filament\Pages\Settlement;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\ManageBudgets;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Resources\Incomes\Pages\ManageIncomes;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\HouseholdBackup;
use App\Services\ReopenMonth;
use App\Services\SettlementCalculator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/** Cover salary weighting, earmarked reserves, reimbursements and their saved reports. */
class IncomeFundingTest extends TestCase
{
    use RefreshDatabase;

    /** Create two participants with equal salaries.
     * @return array{Participant, Participant}
     */
    private function household(): array
    {
        $first = Participant::create(['name' => 'Alex']);
        $second = Participant::create(['name' => 'Sam']);
        foreach ([$first, $second] as $person) {
            $this->income($person, 100000);
        }

        return [$first, $second];
    }

    /** Create a recurring receipt with explicit optional funding attributes.
     * @param  array<string, mixed>  $attributes
     */
    private function income(Participant $recipient, int $cents, array $attributes = []): Income
    {
        return Income::create([...['participant_id' => $recipient->id, 'name' => 'Receipt', 'amount_cents' => $cents, 'start_month' => '2027-01', 'interval_months' => 1], ...$attributes]);
    }

    /** Create an income-weighted bill for both participants.
     * @param  array<string, mixed>  $attributes
     */
    private function expense(Participant $payer, Participant $second, int $cents, array $attributes = []): Expense
    {
        return Expense::create([...[
            'payer_id' => $payer->id, 'name' => 'Bill', 'amount_cents' => $cents,
            'start_month' => '2027-01', 'interval_months' => 1, 'split_mode' => 'income',
            'shares' => [['participant_id' => $payer->id], ['participant_id' => $second->id]],
        ], ...$attributes]);
    }

    /** Non-salary receipts increase cash but leave the cost ratio at 50/50. */
    public function test_other_receipts_do_not_change_salary_weighting(): void
    {
        [$first, $second] = $this->household();
        $this->income($first, 25900, ['affects_allocation' => false]);
        $this->expense($first, $second, 10000);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame(200000, $report['allocation_income_cents']);
        $this->assertSame(25900, $report['other_income_cents']);
        $this->assertSame(225900, $report['total_income_cents']);
        $this->assertSame([5000, 5000], array_column($report['participants'], 'share_cents'));
        $this->assertSame(5000, $report['transfers'][0]['amount_cents']);
        $this->assertSame(120900, $report['participants'][0]['remaining_cents']);
    }

    /** Recurring earmarked receipts stop at their end month and carry reserves across a year boundary. */
    public function test_budget_receipts_follow_intervals_and_end_dates_across_years(): void
    {
        [$first, $second] = $this->household();
        $budget = Budget::factory()->create();
        $this->income($first, 10000, ['budget_id' => $budget->id, 'start_month' => '2026-12', 'end_month' => '2027-03', 'interval_months' => 3]);
        $this->expense($first, $second, 15000, ['budget_id' => $budget->id, 'interval_months' => 0]);

        $january = app(SettlementCalculator::class)->calculate('2027-01');
        $july = app(SettlementCalculator::class)->calculate('2027-07');

        $this->assertSame(10000, $january['budgets'][0]['opening_cents']);
        $this->assertSame(0, $january['budgets'][0]['income_cents']);
        $this->assertSame(5000, $january['expenses'][0]['uncovered_cents']);
        $this->assertSame(10000, $july['budgets'][0]['opening_cents']);
        $this->assertSame(0, $july['budgets'][0]['income_cents']);
        $this->assertSame(10000, $july['budgets'][0]['closing_cents']);
    }

    /** Spending tiny reserves from multiple account holders never loses or creates a cent. */
    public function test_budget_rounding_preserves_each_cent_across_months(): void
    {
        [$first, $second] = $this->household();
        $budget = Budget::factory()->create();
        $this->income($first, 1, ['budget_id' => $budget->id, 'interval_months' => 0]);
        $this->income($second, 2, ['budget_id' => $budget->id, 'interval_months' => 0]);
        $this->expense($first, $second, 2, ['budget_id' => $budget->id, 'interval_months' => 0]);
        $this->expense($first, $second, 1, ['budget_id' => $budget->id, 'start_month' => '2027-02', 'interval_months' => 0]);

        $january = app(SettlementCalculator::class)->calculate('2027-01');
        $february = app(SettlementCalculator::class)->calculate('2027-02');

        $this->assertSame([$first->id => 0, $second->id => 1], $january['budgets'][0]['balances']);
        $this->assertSame(2, $january['expenses'][0]['budget_cents']);
        $this->assertSame(1, $january['transfers'][0]['amount_cents']);
        $this->assertSame(1, $february['budgets'][0]['opening_cents']);
        $this->assertSame(0, $february['budgets'][0]['closing_cents']);
        $this->assertSame(1, $february['expenses'][0]['budget_cents']);
        $this->assertSame(1, $february['transfers'][0]['amount_cents']);
    }

    /** A pool reserved for another purpose cannot cover this bill. */
    public function test_separate_budgets_do_not_share_reserved_money(): void
    {
        [$first, $second] = $this->household();
        $funded = Budget::factory()->create();
        $empty = Budget::factory()->create();
        $this->income($first, 10000, ['budget_id' => $funded->id]);
        $this->expense($first, $second, 6000, ['budget_id' => $empty->id]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame(10000, $report['budgets'][0]['closing_cents']);
        $this->assertSame(0, $report['budgets'][1]['closing_cents']);
        $this->assertSame(0, $report['expenses'][0]['budget_cents']);
        $this->assertSame(6000, $report['expenses'][0]['uncovered_cents']);
        $this->assertSame(3000, $report['transfers'][0]['amount_cents']);
    }

    /** Child expenses consume benefit money and preserve the remaining reserve next month. */
    public function test_child_budget_covers_costs_and_carries_unspent_money_forward(): void
    {
        [$first, $second] = $this->household();
        $budget = Budget::factory()->create(['name' => 'Child']);
        $this->income($first, 25900, ['budget_id' => $budget->id, 'interval_months' => 0]);
        $this->expense($first, $second, 23000, ['budget_id' => $budget->id, 'interval_months' => 0]);
        $this->expense($first, $second, 5000, ['budget_id' => $budget->id, 'start_month' => '2027-02', 'interval_months' => 0]);

        $january = app(SettlementCalculator::class)->calculate('2027-01');
        $february = app(SettlementCalculator::class)->calculate('2027-02');

        $this->assertSame([], $january['transfers']);
        $this->assertSame(2900, $january['budgets'][0]['closing_cents']);
        $this->assertSame([100000, 100000], array_column($january['participants'], 'remaining_cents'));
        $this->assertSame(2900, $february['budgets'][0]['opening_cents']);
        $this->assertSame(0, $february['budgets'][0]['closing_cents']);
        $this->assertSame(2900, $february['expenses'][0]['budget_cents']);
        $this->assertSame(2100, $february['expenses'][0]['uncovered_cents']);
        $this->assertSame(1050, $february['transfers'][0]['amount_cents']);
        $this->assertSame([98950, 98950], array_column($february['participants'], 'remaining_cents'));
    }

    /** Reimbursements reduce the bill and follow the recipient even when someone else pays. */
    public function test_reimbursement_is_credited_to_its_actual_account_holder(): void
    {
        [$first, $second] = $this->household();
        $expense = $this->expense($second, $first, 2000);
        $this->income($first, 500, ['expense_id' => $expense->id]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame(1500, $report['expenses'][0]['uncovered_cents']);
        $this->assertSame([1250, 750], array_column($report['participants'], 'share_cents'));
        $this->assertSame($first->id, $report['transfers'][0]['from_id']);
        $this->assertSame($second->id, $report['transfers'][0]['to_id']);
        $this->assertSame(1250, $report['transfers'][0]['amount_cents']);
        $this->assertSame([99250, 99250], array_column($report['participants'], 'remaining_cents'));
    }

    /** Pools retain each recipient's cash and reimbursements are used before budget funds. */
    public function test_multiple_recipients_and_combined_funding_preserve_balanced_transfers(): void
    {
        [$first, $second] = $this->household();
        $budget = Budget::factory()->create();
        $this->income($first, 10000, ['budget_id' => $budget->id]);
        $this->income($second, 20000, ['budget_id' => $budget->id]);
        $expense = $this->expense($first, $second, 20000, ['budget_id' => $budget->id]);
        $this->income($first, 5000, ['expense_id' => $expense->id]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame(15000, $report['expenses'][0]['budget_cents']);
        $this->assertSame(5000, $report['expenses'][0]['reimbursed_cents']);
        $this->assertSame([$first->id => 5000, $second->id => 10000], $report['budgets'][0]['balances']);
        $this->assertSame(10000, $report['transfers'][0]['amount_cents']);
        $this->assertSame([100000, 100000], array_column($report['participants'], 'remaining_cents'));
    }

    /** A fully funded bill needs no salary and its frozen PDF renders without division by zero. */
    public function test_fully_funded_costs_need_no_salary_and_render_a_pdf(): void
    {
        $person = Participant::create(['name' => 'Alex']);
        $budget = Budget::factory()->create();
        $this->income($person, 25900, ['budget_id' => $budget->id]);
        $this->expense($person, $person, 23000, ['budget_id' => $budget->id, 'shares' => [['participant_id' => $person->id]]]);
        $admin = User::factory()->create(['is_admin' => true]);

        $closing = app(CloseMonth::class)->close('2027-01', $admin->id);

        $this->assertSame([], $closing->snapshot['transfers']);
        $this->assertSame(0, $closing->snapshot['remaining_cents']);
        $this->actingAs($admin)->get(route('closings.pdf', $closing))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    /** Earmarked money cannot substitute for salary when uncovered income-weighted costs remain. */
    public function test_uncovered_costs_still_require_allocation_income(): void
    {
        $person = Participant::create(['name' => 'Alex']);
        $this->income($person, 25900, ['affects_allocation' => false]);
        $this->expense($person, $person, 10000, ['shares' => [['participant_id' => $person->id]]]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('settlement.calculator.missing_income', ['name' => 'Bill']));
        app(SettlementCalculator::class)->calculate('2027-01');
    }

    /** Invalid reimbursement amounts and schedules prevent an incorrect monthly settlement. */
    #[DataProvider('invalidRefunds')]
    public function test_invalid_reimbursements_are_reported(int $amount, string $startMonth, string $message): void
    {
        [$first, $second] = $this->household();
        $expense = $this->expense($first, $second, 2000, ['interval_months' => 0]);
        $this->income($first, $amount, ['name' => 'Refund', 'expense_id' => $expense->id, 'start_month' => $startMonth]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__($message, ['name' => $startMonth === '2027-01' ? 'Bill' : 'Refund']));
        app(SettlementCalculator::class)->calculate($startMonth);
    }

    /** Invalid refunds exercise excessive amounts and missing matching scheduled bills.
     * @return array<string, array{int, string, string}>
     */
    public static function invalidRefunds(): array
    {
        return ['excessive' => [2001, '2027-01', 'funding.refund_exceeds_cost'], 'wrong month' => [500, '2027-02', 'funding.refund_not_due']];
    }

    /** A receipt cannot fund both a pool and a separate bill simultaneously. */
    public function test_ambiguous_funding_destinations_are_rejected_without_saving(): void
    {
        [$first, $second] = $this->household();
        $budget = Budget::factory()->create();
        $expense = $this->expense($first, $second, 2000);
        try {
            $this->income($first, 500, ['budget_id' => $budget->id, 'expense_id' => $expense->id]);
            $this->fail('Ambiguous receipt accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('budget_id', $exception->errors());
        }
        $this->assertDatabaseCount('incomes', 2);
    }

    /** Frozen closing reserves anchor later months even if the original benefit is edited. */
    public function test_frozen_carryovers_survive_source_edits_and_reopening_requires_reverse_order(): void
    {
        [$first, $second] = $this->household();
        $admin = User::factory()->create(['is_admin' => true]);
        $budget = Budget::factory()->create();
        $income = $this->income($first, 25900, ['budget_id' => $budget->id, 'interval_months' => 0]);
        $this->expense($first, $second, 23000, ['budget_id' => $budget->id, 'interval_months' => 0]);
        $january = app(CloseMonth::class)->close('2027-01', $admin->id);
        $income->update(['amount_cents' => 30000]);

        $february = app(CloseMonth::class)->close('2027-02', $admin->id);

        $this->assertSame(2900, $february->snapshot['budgets'][0]['opening_cents']);
        $this->assertSame(2900, $january->fresh()->snapshot['budgets'][0]['closing_cents']);
        try {
            app(ReopenMonth::class)->reopen('2027-01');
            $this->fail('Dependent closing allowed reopening');
        } catch (ValidationException $exception) {
            $this->assertSame(__('funding.later_closing'), $exception->getMessage());
        }
        $this->assertNotNull($january->fresh()->closed_at);
        app(ReopenMonth::class)->reopen('2027-02');
        app(ReopenMonth::class)->reopen('2027-01');
        $this->assertSame(7000, app(SettlementCalculator::class)->calculate('2027-02')['budgets'][0]['opening_cents']);
    }

    /** Backups preserve budget references and old backups receive the legacy salary default. */
    public function test_new_and_legacy_backups_restore_funding_safely(): void
    {
        [$first, $second] = $this->household();
        $budget = Budget::factory()->create();
        $expense = $this->expense($first, $second, 23000, ['budget_id' => $budget->id]);
        $this->income($first, 25900, ['budget_id' => $budget->id]);
        $this->income($first, 500, ['expense_id' => $expense->id]);
        $expected = app(SettlementCalculator::class)->calculate('2027-02');
        $path = tempnam(sys_get_temp_dir(), 'funding-backup-');
        try {
            app(HouseholdBackup::class)->exportToZip($path);
            app(HouseholdBackup::class)->importFromZip($path);
            $this->assertSame($expected, app(SettlementCalculator::class)->calculate('2027-02'));

            $zip = new ZipArchive;
            $zip->open($path);
            $zip->deleteName('budgets.json');
            $zip->addFromString('manifest.json', json_encode(['app' => 'splitnest', 'format' => 1]));
            foreach (['incomes', 'expenses'] as $table) {
                $rows = json_decode($zip->getFromName($table.'.json'), true);
                foreach ($rows as &$row) {
                    unset($row['budget_id'], $row['expense_id'], $row['affects_allocation']);
                }
                unset($row);
                $zip->addFromString($table.'.json', json_encode($rows));
            }
            $zip->close();
            app(HouseholdBackup::class)->importFromZip($path);
            $this->assertDatabaseCount('budgets', 0);
            $this->assertSame(4, Income::where('affects_allocation', true)->count());
        } finally {
            unlink($path);
        }
    }

    /** Admin forms persist funding links; dashboard, participant view and PDF expose their meaning. */
    public function test_forms_and_monthly_views_show_the_funding_breakdown(): void
    {
        [$first, $second] = $this->household();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageBudgets::class)->callAction('create', data: ['name' => 'Child'])->assertHasNoActionErrors();
        $budget = Budget::sole();
        Livewire::test(ManageIncomes::class)->callAction('create', data: [
            'name' => 'Child benefit', 'participant_id' => $first->id, 'amount' => '259',
            'start_month' => '2027-01', 'interval_months' => 1, 'budget_id' => $budget->id,
        ])->assertHasNoActionErrors();
        Livewire::test(ManageExpenses::class)->callAction('create', data: [
            'name' => 'Nursery', 'payer_id' => $first->id, 'amount' => '230',
            'start_month' => '2027-01', 'interval_months' => 1, 'budget_id' => $budget->id,
            'split_mode' => 'equal', 'shares' => [['participant_id' => $first->id], ['participant_id' => $second->id]],
        ])->assertHasNoActionErrors();

        $this->assertDatabaseHas('incomes', ['name' => 'Child benefit', 'affects_allocation' => false, 'budget_id' => $budget->id]);
        $this->assertDatabaseHas('expenses', ['name' => 'Nursery', 'budget_id' => $budget->id]);
        $this->get(Settlement::getUrl(['month' => '2027-01']))->assertOk()->assertSee(__('funding.budget_summary'))->assertSee('29,00 €');
        $this->get(ParticipantMonth::getUrl(['participant' => $first->id, 'month' => '2027-01']))->assertOk()->assertSee(__('funding.not_relevant'));
        $closing = app(CloseMonth::class)->close('2027-01', $admin->id);
        $html = view('pdf.closing', ['closing' => $closing, 'report' => $closing->snapshot])->render();
        $this->assertStringContainsString(__('funding.budget_summary'), $html);
        $this->assertStringContainsString('29,00 €', $html);
    }

    /** Budget management uses the same household admin authorization as the other resources. */
    public function test_regular_members_cannot_manage_budgets(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(BudgetResource::getUrl())->assertForbidden();
    }

    /** Budget cents are consumed once, in creation order, with deterministic remainder rounding. */
    public function test_small_budget_is_consumed_once_before_splitting_later_bills(): void
    {
        [$first, $second] = $this->household();
        $budget = Budget::factory()->create();
        $this->income($first, 1, ['budget_id' => $budget->id]);
        $this->expense($first, $second, 1, ['budget_id' => $budget->id]);
        $this->expense($first, $second, 3, ['budget_id' => $budget->id]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame([1, 0], array_column($report['expenses'], 'budget_cents'));
        $this->assertSame([0, 3], array_column($report['expenses'], 'uncovered_cents'));
        $this->assertSame([3, 1], array_column($report['participants'], 'share_cents'));
        $this->assertSame(0, $report['budgets'][0]['closing_cents']);
        $this->assertSame(1, $report['transfers'][0]['amount_cents']);
    }

    /** Closing an earlier month cannot invalidate an already frozen future reserve. */
    public function test_closing_before_a_frozen_budget_month_is_rejected_atomically(): void
    {
        $this->household();
        Budget::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        app(CloseMonth::class)->close('2027-02', $admin->id);

        try {
            app(CloseMonth::class)->close('2027-01', $admin->id);
            $this->fail('Earlier closing accepted');
        } catch (ValidationException $exception) {
            $this->assertSame(__('funding.later_closing'), $exception->getMessage());
        }
        $this->assertDatabaseMissing('monthly_closings', ['month' => '2027-01']);
        $this->assertDatabaseCount('monthly_closings', 1);
    }

    /** Modern backups must include budgets so restoring cannot silently erase their reserves. */
    public function test_modern_backup_missing_budgets_is_rejected_without_replacing_data(): void
    {
        $budget = Budget::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'funding-backup-');
        try {
            app(HouseholdBackup::class)->exportToZip($path);
            $zip = new ZipArchive;
            $zip->open($path);
            $zip->deleteName('budgets.json');
            $zip->close();
            try {
                app(HouseholdBackup::class)->importFromZip($path);
                $this->fail('Incomplete modern backup accepted');
            } catch (ValidationException $exception) {
                $this->assertSame(__('backup.errors.missing_table', ['table' => 'budgets']), $exception->getMessage());
            }
            $this->assertModelExists($budget);
        } finally {
            unlink($path);
        }
    }

    /** Legacy snapshots lacking all funding fields still render without reading changed live data. */
    public function test_legacy_snapshots_render_after_the_funding_upgrade(): void
    {
        [$first, $second] = $this->household();
        $this->expense($first, $second, 10001);
        $report = app(SettlementCalculator::class)->calculate('2027-01');
        $report['version'] = 1;
        unset($report['budgets'], $report['allocation_income_cents'], $report['other_income_cents'], $report['remaining_cents']);
        foreach ($report['participants'] as &$person) {
            unset($person['allocation_income_cents'], $person['other_income_cents'], $person['reserved_cents'], $person['opening_reserved_cents']);
        }
        unset($person);
        foreach ($report['incomes'] as &$income) {
            unset($income['affects_allocation'], $income['budget_name'], $income['expense_name']);
        }
        unset($income);
        foreach ($report['expenses'] as &$expense) {
            unset($expense['reimbursed_cents'], $expense['budget_cents'], $expense['uncovered_cents']);
        }
        unset($expense);
        $admin = User::factory()->create(['is_admin' => true]);
        $closing = MonthlyClosing::create(['month' => '2027-01', 'snapshot' => $report, 'closed_at' => now(), 'closed_by' => $admin->id]);
        Income::query()->update(['amount_cents' => 500000]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(Settlement::getUrl(['month' => '2027-01']))->assertOk()->assertSee('2.000,00 €');
        $this->get(ParticipantMonth::getUrl(['participant' => $first->id, 'month' => '2027-01']))->assertOk();
        $this->get(route('closings.pdf', $closing))->assertOk()->assertHeader('content-type', 'application/pdf');
        $html = view('pdf.closing', ['closing' => $closing, 'report' => $report])->render();
        $this->assertStringContainsString('50,00 %', $html);
        $this->assertStringNotContainsString(__('funding.budget_summary'), $html);
    }

    /** User-provided pool names are escaped in the dashboard and statement HTML. */
    public function test_budget_names_are_escaped_in_monthly_views_and_pdf_html(): void
    {
        $this->household();
        $budget = Budget::factory()->create(['name' => '<script>alert("budget")</script>']);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $closing = app(CloseMonth::class)->close('2027-01', $admin->id);

        $this->get(Settlement::getUrl(['month' => '2027-01']))->assertOk()->assertSee($budget->name)->assertDontSee($budget->name, false);
        $html = view('pdf.closing', ['closing' => $closing, 'report' => $closing->snapshot])->render();
        $this->assertStringContainsString(e($budget->name), $html);
        $this->assertStringNotContainsString($budget->name, $html);
    }

    /** The reimbursement form excludes salary weighting and protects its linked bill from deletion. */
    public function test_reimbursement_form_persists_its_bill_and_prevents_dangling_receipts(): void
    {
        [$first, $second] = $this->household();
        $expense = $this->expense($first, $second, 2000);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageIncomes::class)->callAction('create', data: [
            'name' => 'Netflix contribution', 'participant_id' => $first->id, 'amount' => '5',
            'expense_id' => $expense->id, 'start_month' => '2027-01', 'interval_months' => 1,
        ])->assertHasNoActionErrors();

        $this->assertDatabaseHas('incomes', ['name' => 'Netflix contribution', 'expense_id' => $expense->id, 'affects_allocation' => false]);
        Livewire::test(ManageExpenses::class)->assertTableActionDisabled('delete', $expense);
        $this->assertModelExists($expense);
    }
}
