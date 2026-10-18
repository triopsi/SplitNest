<?php

namespace Tests\Feature;

use App\Filament\Pages\ParticipantMonth;
use App\Filament\Pages\Settlement;
use App\Filament\Widgets\MonthlyCostSharesChart;
use App\Filament\Widgets\MonthlyExpensesTable;
use App\Filament\Widgets\MonthlyExpenseStat;
use App\Filament\Widgets\MonthlyIncomeStat;
use App\Filament\Widgets\MonthlyParticipantsTable;
use App\Filament\Widgets\MonthlyRemainingStat;
use App\Filament\Widgets\MonthlyTransfers;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Monthly dashboard coverage: widget data, frozen closings, empty states and escaping.
 */
class SettlementDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Activate the admin panel and isolate closing e-mails from external delivery.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Mail::fake();
    }

    /**
     * Create two participants with monthly incomes and an equally split January expense.
     *
     * @return array{Participant, Participant, Expense}
     */
    private function household(): array
    {
        $first = Participant::create(['name' => 'Alex']);
        $second = Participant::create(['name' => 'Sam']);
        foreach ([[$first, '1000'], [$second, '2000']] as [$participant, $amount]) {
            Income::create(['participant_id' => $participant->id, 'name' => 'Salary', 'amount' => $amount, 'start_month' => '2027-01', 'interval_months' => 1]);
        }
        $expense = Expense::create([
            'name' => 'January rent', 'payer_id' => $first->id, 'amount' => '600', 'split_mode' => 'equal',
            'shares' => [['participant_id' => $first->id], ['participant_id' => $second->id]],
            'start_month' => '2027-01', 'end_month' => '2027-01', 'interval_months' => 1,
        ]);

        return [$first, $second, $expense];
    }

    /**
     * Open a populated monthly dashboard as an administrator.
     *
     * Expects all seven widgets, accurate monetary values and shares, and a link to the participant month.
     */
    public function test_dashboard_renders_all_widgets_with_monthly_values_and_participant_links(): void
    {
        [$first] = $this->household();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(Settlement::getUrl(['month' => '2027-01']))
            ->assertOk()
            ->assertSeeLivewire(MonthlyIncomeStat::class)
            ->assertSeeLivewire(MonthlyExpenseStat::class)
            ->assertSeeLivewire(MonthlyRemainingStat::class)
            ->assertSeeLivewire(MonthlyCostSharesChart::class)
            ->assertSeeLivewire(MonthlyTransfers::class)
            ->assertSeeLivewire(MonthlyParticipantsTable::class)
            ->assertSeeLivewire(MonthlyExpensesTable::class)
            ->assertSee('3.000,00 €')
            ->assertSee('600,00 €')
            ->assertSee('2.400,00 €')
            ->assertSee('300,00 €')
            ->assertSee('50 %')
            ->assertSee(ParticipantMonth::getUrl(['participant' => $first->id, 'month' => '2027-01']), false);
    }

    /**
     * Switch the dashboard from January to February and then to the previous year.
     *
     * Expects widget data to follow the selected month without retaining earlier expenses, transfers or income.
     */
    public function test_month_and_year_pickers_supply_updated_reports_to_every_widget(): void
    {
        $this->household();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = Livewire::test(Settlement::class, ['month' => '2027-01']);
        $this->assertSame(60000, $page->instance()->getWidgetData()['report']['total_expense_cents']);

        $page->set('monthPicker', '2');
        $data = $page->instance()->getWidgetData();
        $this->assertSame('2027-02', $data['month']);
        $this->assertSame(0, $data['report']['total_expense_cents']);
        $this->assertSame([], $data['report']['expenses']);
        $this->assertSame([], $data['report']['transfers']);

        $page->set('yearPicker', '2026');
        $data = $page->instance()->getWidgetData();
        $this->assertSame('2026-02', $data['month']);
        $this->assertSame(0, $data['report']['total_income_cents']);
    }

    /**
     * Close January, change source data and inspect the widgets before and after reopening.
     *
     * Expects the frozen names, amounts and allocations until reopening restores live calculation.
     */
    public function test_widgets_keep_frozen_names_and_values_until_the_month_is_reopened(): void
    {
        [$first, $second, $expense] = $this->household();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        app(CloseMonth::class)->close('2027-01', $admin->id);
        $first->update(['name' => 'Changed name']);
        $expense->update(['amount' => '900']);
        $page = Livewire::test(Settlement::class, ['month' => '2027-01']);
        $data = $page->instance()->getWidgetData();

        Livewire::test(MonthlyExpenseStat::class, $data)->assertSee('600,00 €');
        Livewire::test(MonthlyTransfers::class, $data)->assertSee('Alex')->assertSee('300,00 €');
        Livewire::test(MonthlyParticipantsTable::class, $data)
            ->assertTableColumnStateSet('share_cents', 30000, $first->id)
            ->assertSee('Alex')
            ->assertDontSee('Changed name');
        Livewire::test(MonthlyExpensesTable::class, $data)
            ->assertTableColumnStateSet('amount_cents', 60000, $expense->id)
            ->assertTableColumnStateSet('allocation_'.$second->id, 30000, $expense->id);
        $chart = Livewire::test(MonthlyCostSharesChart::class, $data)->instance()->getData();
        $this->assertSame(['Alex', 'Sam'], $chart['labels']);
        $this->assertSame([300.0, 300.0], $chart['datasets'][0]['data']);

        $page->callAction('reopenMonth');
        $data = $page->instance()->getWidgetData();
        $this->assertSame(90000, $data['report']['total_expense_cents']);
        $this->assertSame('Changed name', $data['report']['participants'][0]['name']);
    }

    /**
     * Open the monthly dashboard without household participants.
     *
     * Expects participant setup guidance and no report widgets to be mounted.
     */
    public function test_missing_participants_show_guidance_without_mounting_report_widgets(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(Settlement::getUrl(['month' => '2027-01']))
            ->assertOk()
            ->assertSee(__('settlement.calculator.no_participants'))
            ->assertDontSeeLivewire(MonthlyIncomeStat::class)
            ->assertDontSeeLivewire(MonthlyCostSharesChart::class)
            ->assertDontSeeLivewire(MonthlyExpensesTable::class);
    }

    /**
     * Open a month with income but no applicable expenses.
     *
     * Expects a balanced-transfer message, an empty expense table and the current income total.
     */
    public function test_month_without_expenses_renders_balanced_and_empty_states(): void
    {
        $this->household();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(Settlement::getUrl(['month' => '2027-02']))
            ->assertOk()
            ->assertSee(__('settlement.transfers.balanced'))
            ->assertSee(__('settlement.costs.empty'))
            ->assertSee('3.000,00 €')
            ->assertDontSee('January rent');
    }

    /**
     * Render participant and expense names containing script markup.
     *
     * Expects escaped text in the dashboard and no unescaped script markup.
     */
    public function test_widget_tables_escape_participant_and_expense_names(): void
    {
        [$first, $second, $expense] = $this->household();
        $first->update(['name' => '<script>alert("participant")</script>']);
        $expense->update(['name' => '<script>alert("expense")</script>']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(Settlement::getUrl(['month' => '2027-01']))
            ->assertOk()
            ->assertSee($first->name)
            ->assertSee($expense->name)
            ->assertDontSee($first->name, false)
            ->assertDontSee($expense->name, false);
    }
}
