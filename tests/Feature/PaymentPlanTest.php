<?php

namespace Tests\Feature;

use App\Filament\Pages\Settlement;
use App\Filament\Resources\Incomes\Pages\ManageIncomes;
use App\Filament\Widgets\MonthlyBudgets;
use App\Filament\Widgets\MonthlyTransfers;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\HouseholdBackup;
use App\Services\SettlementCalculator;
use App\Support\MonthlyDashboardLayout;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Expected receipt payments augment household settlement without changing income or cost weights. */
class PaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    /** Create equal salaries and a shared 600 euro bill paid by Max.
     * @return array{Participant, Participant}
     */
    private function household(): array
    {
        $max = Participant::create(['name' => 'Max']);
        $erika = Participant::create(['name' => 'Erika']);
        foreach ([$max, $erika] as $person) {
            $this->income($person, 100000);
        }
        Expense::create([
            'name' => 'Rent', 'payer_id' => $max->id, 'amount_cents' => 60000,
            'split_mode' => 'income', 'shares' => [['participant_id' => $max->id], ['participant_id' => $erika->id]],
            'start_month' => '2027-01', 'interval_months' => 1,
        ]);

        return [$max, $erika];
    }

    /** Record a scheduled receipt with optional payer and budget fields.
     * @param  array<string, mixed>  $attributes
     */
    private function income(Participant $recipient, int $amount, array $attributes = []): Income
    {
        return Income::create([
            'name' => 'Salary', 'participant_id' => $recipient->id, 'amount_cents' => $amount,
            'start_month' => '2027-01', 'interval_months' => 1, ...$attributes,
        ]);
    }

    /** Forwarded subsidy is added to the payment, never to salary or income a second time. */
    public function test_subsidy_is_added_to_erikas_transfer_with_a_separate_reason(): void
    {
        [$max, $erika] = $this->household();
        $budget = Budget::factory()->create(['name' => 'Child']);
        $this->income($max, 9400, ['name' => 'Childcare subsidy', 'sender_id' => $erika->id, 'budget_id' => $budget->id, 'include_in_payment_plan' => true]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame(200000, $report['allocation_income_cents']);
        $this->assertSame(209400, $report['total_income_cents']);
        $this->assertSame(9400, $report['budgets'][0]['closing_cents']);
        $this->assertSame(30000, $report['transfers'][0]['amount_cents']);
        $this->assertCount(1, $report['payment_instructions']);
        $this->assertSame(39400, $report['payment_instructions'][0]['amount_cents']);
        $this->assertSame(30000, $report['payment_instructions'][0]['settlement_cents']);
        $this->assertSame([['name' => 'Childcare subsidy', 'amount_cents' => 9400]], $report['payment_instructions'][0]['reasons']);
        Livewire::test(MonthlyTransfers::class, ['month' => '2027-01', 'report' => $report])
            ->assertSee('Erika')->assertSee('Max')->assertSee('394,00 €')->assertSee('94,00 €')->assertSee('300,00 €');
    }

    /** A friend's reimbursement appears without making the friend a household participant. */
    public function test_external_netflix_payment_is_displayed_without_creating_a_household_member(): void
    {
        [$max, $erika] = $this->household();
        $netflix = Expense::create([
            'name' => 'Netflix', 'payer_id' => $max->id, 'amount_cents' => 2000, 'split_mode' => 'equal',
            'shares' => [['participant_id' => $max->id], ['participant_id' => $erika->id]],
            'start_month' => '2027-01', 'interval_months' => 1,
        ]);
        $this->income($max, 499, ['name' => 'Netflix contribution', 'sender_name' => 'Robin', 'expense_id' => $netflix->id, 'include_in_payment_plan' => true]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertDatabaseCount('participants', 2);
        $this->assertSame(200499, $report['total_income_cents']);
        $this->assertSame(200000, $report['allocation_income_cents']);
        $this->assertSame(1501, $report['expenses'][1]['uncovered_cents']);
        $this->assertSame(30750, $report['transfers'][0]['amount_cents']);
        $external = $report['payment_instructions'][1];
        $this->assertNull($external['from_id']);
        $this->assertSame('Robin', $external['from_name']);
        $this->assertSame($max->id, $external['to_id']);
        $this->assertSame(499, $external['amount_cents']);
        Livewire::test(MonthlyTransfers::class, ['month' => '2027-01', 'report' => $report])->assertSee('Robin')->assertSee('4,99 €');
    }

    /** Separately handled receipts do not request another transfer and do not alter salary weighting. */
    public function test_disabled_payment_plan_keeps_the_source_without_requesting_an_extra_transfer(): void
    {
        [$max, $erika] = $this->household();
        $receipt = $this->income($max, 9400, ['sender_id' => $erika->id]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertFalse($receipt->affects_allocation);
        $this->assertSame(200000, $report['allocation_income_cents']);
        $this->assertCount(1, $report['payment_instructions']);
        $this->assertSame(30000, $report['payment_instructions'][0]['amount_cents']);
        $this->assertSame([], $report['payment_instructions'][0]['reasons']);
    }

    /** Only scheduled months include expected payments, with zero amounts omitted. */
    public function test_payment_plan_follows_schedule_boundaries_and_ignores_zero_receipts(): void
    {
        [$max] = $this->household();
        $this->income($max, 499, ['sender_name' => 'Robin', 'include_in_payment_plan' => true, 'interval_months' => 3, 'end_month' => '2027-04']);
        $this->income($max, 0, ['sender_name' => 'Nobody', 'include_in_payment_plan' => true]);

        $calculator = app(SettlementCalculator::class);

        $this->assertCount(2, $calculator->calculate('2027-01')['payment_instructions']);
        $this->assertCount(1, $calculator->calculate('2027-02')['payment_instructions']);
        $this->assertCount(2, $calculator->calculate('2027-04')['payment_instructions']);
        $this->assertCount(1, $calculator->calculate('2027-07')['payment_instructions']);
    }

    /** Invalid payers cannot create ambiguous, self-directed or source-free payment instructions. */
    #[DataProvider('invalidSources')]
    public function test_invalid_payment_sources_are_rejected_without_creating_income(array $attributes, string $field): void
    {
        [$max, $erika] = $this->household();
        if (array_key_exists('sender_id', $attributes)) {
            $attributes['sender_id'] = match ($attributes['sender_id']) {
                'self' => $max->id,
                'household' => $erika->id,
                default => $attributes['sender_id'],
            };
        }
        try {
            $this->income($max, 9400, $attributes);
            $this->fail('Invalid payer accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('incomes', 2);
    }

    /** Invalid sources exercise distinct validation contracts.
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidSources(): array
    {
        return [
            'self' => [['sender_id' => 'self'], 'sender_id'],
            'both sources' => [['sender_id' => 'household', 'sender_name' => 'Robin'], 'sender_id'],
            'missing source' => [['include_in_payment_plan' => true], 'sender_id'],
            'blank external source' => [['include_in_payment_plan' => true, 'sender_name' => '  '], 'sender_id'],
            'unknown household source' => [['sender_id' => 999999], 'sender_id'],
            'oversized external source' => [['sender_name' => str_repeat('x', 256)], 'sender_name'],
        ];
    }

    /** The income form persists explicit payer and plan preferences for forwarding payments. */
    public function test_income_form_saves_household_payer_and_payment_plan(): void
    {
        [$max, $erika] = $this->household();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageIncomes::class)->callAction('create', data: [
            'name' => 'Childcare subsidy', 'participant_id' => $max->id, 'sender_id' => $erika->id,
            'include_in_payment_plan' => true, 'amount' => '94', 'start_month' => '2027-01', 'interval_months' => 1,
        ])->assertHasNoActionErrors();

        $this->assertDatabaseHas('incomes', ['name' => 'Childcare subsidy', 'participant_id' => $max->id, 'sender_id' => $erika->id, 'include_in_payment_plan' => true, 'affects_allocation' => false]);
    }

    /** Closing freezes payment reasons and payer names; budgets remain independent reactive widgets. */
    public function test_payment_instructions_and_budget_widget_use_frozen_monthly_snapshots(): void
    {
        [$max, $erika] = $this->household();
        $budget = Budget::factory()->create(['name' => 'Child']);
        $income = $this->income($max, 9400, ['name' => 'Subsidy', 'sender_id' => $erika->id, 'budget_id' => $budget->id, 'include_in_payment_plan' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $closing = app(CloseMonth::class)->close('2027-01', $admin->id);
        $income->update(['amount_cents' => 15000]);
        $erika->update(['name' => 'Changed sender']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(MonthlyBudgets::class, ['month' => '2027-01'])->assertSee('94,00 €')->assertDontSee('150,00 €');
        Livewire::test(MonthlyTransfers::class, ['month' => '2027-01'])->assertSee('394,00 €')->assertSee('Erika')->assertDontSee('Changed sender');
        $this->get(Settlement::getUrl(['month' => '2027-01']))->assertOk()->assertSeeLivewire(MonthlyBudgets::class);
        $html = view('pdf.closing', ['closing' => $closing, 'report' => $closing->snapshot])->render();
        $this->assertStringContainsString('394,00 €', $html);
        $this->assertStringContainsString('Subsidy', $html);
        $this->get(route('closings.pdf', $closing))->assertOk()->assertHeader('content-type', 'application/pdf');
        Livewire::test(MonthlyBudgets::class, ['month' => '2027-02'])->assertSee('150,00 €')->assertSee('244,00 €');
    }

    /** Legacy layouts keep their personalized positions when the budget widget is appended. */
    public function test_legacy_layout_preserves_positions_and_adds_the_budget_widget(): void
    {
        $legacy = array_values(array_filter(MonthlyDashboardLayout::defaults(), fn (array $item): bool => $item['id'] !== 'budgets'));
        $legacy[0]['x'] = 8;
        $legacy[2]['x'] = 0;
        $user = User::factory()->create(['is_admin' => true, 'monthly_dashboard_layout' => $legacy]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(Settlement::class);
        $layout = collect($page->instance()->dashboardLayout())->keyBy('id');

        $this->assertSame(8, $layout['income']['x']);
        $this->assertSame(0, $layout['remaining']['x']);
        $this->assertSame(28, $layout['budgets']['y']);
        $this->assertCount(8, $layout);
        $this->assertSame($legacy, $user->fresh()->monthly_dashboard_layout);
    }

    /** Budgets missing from old snapshots yield a usable empty widget rather than live balances. */
    public function test_budget_widget_supports_legacy_empty_snapshots(): void
    {
        Livewire::test(MonthlyBudgets::class, ['month' => '2027-01', 'report' => []])->assertSee(__('funding.no_budgets'));
    }

    /** Backups preserve both household source references and the explicit plan setting. */
    public function test_backup_restores_payment_source_and_plan_preference(): void
    {
        [$max, $erika] = $this->household();
        $this->income($max, 9400, ['sender_id' => $erika->id, 'include_in_payment_plan' => true]);
        $expected = app(SettlementCalculator::class)->calculate('2027-01');
        $path = tempnam(sys_get_temp_dir(), 'payment-backup-');
        try {
            app(HouseholdBackup::class)->exportToZip($path);
            app(HouseholdBackup::class)->importFromZip($path);

            $this->assertSame($expected, app(SettlementCalculator::class)->calculate('2027-01'));
        } finally {
            unlink($path);
        }
    }

    /** Payer names containing markup are escaped in the dashboard and PDF statement. */
    public function test_external_payer_names_are_escaped_in_payment_views(): void
    {
        [$max] = $this->household();
        $name = '<script>alert("payer")</script>';
        $this->income($max, 499, ['sender_name' => $name, 'include_in_payment_plan' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $closing = app(CloseMonth::class)->close('2027-01', $admin->id);

        Livewire::test(MonthlyTransfers::class, ['month' => '2027-01'])->assertSee($name)->assertDontSee($name, false);
        $html = view('pdf.closing', ['closing' => $closing, 'report' => $closing->snapshot])->render();
        $this->assertStringContainsString(e($name), $html);
        $this->assertStringNotContainsString($name, $html);
    }

    /** Forwarding remains payable even when budgets cover all expenses and no cost balancing is due. */
    public function test_forwarding_payment_is_shown_without_a_household_balancing_transfer(): void
    {
        [$max, $erika] = $this->household();
        Expense::query()->delete();
        $this->income($max, 9400, ['sender_id' => $erika->id, 'include_in_payment_plan' => true]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertSame([], $report['transfers']);
        $this->assertCount(1, $report['payment_instructions']);
        $this->assertSame(9400, $report['payment_instructions'][0]['amount_cents']);
        $this->assertSame(0, $report['payment_instructions'][0]['settlement_cents']);
        Livewire::test(MonthlyTransfers::class, ['month' => '2027-01', 'report' => $report])->assertSee('94,00 €')->assertDontSee(__('settlement.transfers.balanced'));
    }

    /** A forwarding instruction opposite to balancing remains explicit rather than counted twice or lost. */
    public function test_opposite_direction_payments_keep_their_distinct_reasons(): void
    {
        [$max, $erika] = $this->household();
        $this->income($erika, 9400, ['sender_id' => $max->id, 'include_in_payment_plan' => true]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertCount(2, $report['payment_instructions']);
        $this->assertSame([$erika->id, $max->id], array_column($report['payment_instructions'], 'from_id'));
        $this->assertSame([30000, 9400], array_column($report['payment_instructions'], 'amount_cents'));
        $this->assertSame([0, 9400], array_column($report['payment_instructions'], 'income_cents'));
    }

    /** Several receipts from the same external payer share one payment instruction with separate reasons. */
    public function test_external_receipts_are_grouped_by_payer_and_recipient(): void
    {
        [$max] = $this->household();
        $this->income($max, 499, ['name' => 'Netflix', 'sender_name' => 'Robin', 'include_in_payment_plan' => true, 'affects_allocation' => false]);
        $this->income($max, 500, ['name' => 'Other contribution', 'sender_name' => 'Robin', 'include_in_payment_plan' => true, 'affects_allocation' => false]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertCount(2, $report['payment_instructions']);
        $this->assertSame(999, $report['payment_instructions'][1]['amount_cents']);
        $this->assertSame(['Netflix', 'Other contribution'], array_column($report['payment_instructions'][1]['reasons'], 'name'));
    }

    /** Matching display names cannot combine distinct payers or payments to different recipients. */
    public function test_identical_payer_names_do_not_merge_household_and_external_obligations(): void
    {
        [$first, $second] = $this->household();
        $this->income($first, 499, ['sender_name' => $second->name, 'include_in_payment_plan' => true, 'affects_allocation' => false]);
        $this->income($second, 600, ['sender_name' => $second->name, 'include_in_payment_plan' => true, 'affects_allocation' => false]);

        $report = app(SettlementCalculator::class)->calculate('2027-01');

        $this->assertCount(3, $report['payment_instructions']);
        $this->assertSame([$second->id, null, null], array_column($report['payment_instructions'], 'from_id'));
        $this->assertSame([$first->id, $first->id, $second->id], array_column($report['payment_instructions'], 'to_id'));
        $this->assertSame([30000, 499, 600], array_column($report['payment_instructions'], 'amount_cents'));
        $this->assertSame([30000, 30000], array_column($report['participants'], 'share_cents'));
    }

    /** External payer names can be entered through the same income form as household sources. */
    public function test_income_form_saves_an_external_payer(): void
    {
        [$max] = $this->household();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageIncomes::class)->callAction('create', data: [
            'name' => 'Contribution', 'participant_id' => $max->id, 'sender_name' => 'Robin',
            'include_in_payment_plan' => true, 'affects_allocation' => false,
            'amount' => '4.99', 'start_month' => '2027-01', 'interval_months' => 1,
        ])->assertHasNoActionErrors();

        $this->assertDatabaseHas('incomes', ['name' => 'Contribution', 'sender_name' => 'Robin', 'sender_id' => null, 'include_in_payment_plan' => true]);
    }
}
