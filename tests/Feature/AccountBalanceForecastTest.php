<?php

namespace Tests\Feature;

use App\Filament\Pages\ParticipantMonth;
use App\Filament\Pages\YearPlan;
use App\Filament\Resources\Participants\Pages\ManageParticipants;
use App\Filament\Widgets\MonthlyParticipantsTable;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use App\Models\User;
use App\Services\AccountBalanceForecast;
use App\Services\CloseMonth;
use App\Services\HouseholdBackup;
use App\Services\SettlementCalculator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Observed balances carry debt across months without altering salary weights, transfers or saved reports. */
class AccountBalanceForecastTest extends TestCase
{
    use RefreshDatabase;

    /** Create a participant with a known negative month-end balance and a recurring salary. */
    private function participant(): Participant
    {
        $person = Participant::create(['name' => 'Alex', 'account_balance_month' => '2026-12', 'account_balance' => '-500,00']);
        Income::create(['name' => 'Salary', 'participant_id' => $person->id, 'amount_cents' => 100000, 'start_month' => '2027-01', 'interval_months' => 1]);

        return $person;
    }

    /** Create a recurring personal expense, optionally as savings or a funded bill.
     * @param  array<string, mixed>  $attributes
     */
    private function expense(Participant $person, int $amount, array $attributes = []): Expense
    {
        return Expense::create(['name' => 'Rent', 'payer_id' => $person->id, 'amount_cents' => $amount, 'start_month' => '2027-01', 'interval_months' => 1, 'split_mode' => 'personal', 'shares' => [['participant_id' => $person->id]], ...$attributes]);
    }

    /** Long savings forecasts reuse loaded records and prior balances instead of repeating historical validation queries. */
    public function test_multi_year_savings_forecast_keeps_database_work_bounded(): void
    {
        $person = $this->participant();
        $this->expense($person, 20000, ['savings_budget_id' => Budget::factory()->create()->id]);
        DB::enableQueryLog();
        try {
            app(AccountBalanceForecast::class)->between('2027-01', '2027-12');
            $shortQueryCount = count(DB::getQueryLog());
            DB::flushQueryLog();
            $forecast = app(AccountBalanceForecast::class)->between('2031-12', '2031-12');
            $longQueryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame(5950000, $forecast['2031-12'][$person->id]['balance_cents']);
        $this->assertSame(4750000, $forecast['2031-12'][$person->id]['available_cents']);
        $this->assertLessThanOrEqual($shortQueryCount * 2 + 20, $longQueryCount);
    }

    /** A monthly surplus reduces debt cumulatively and records the first nonnegative month. */
    public function test_debt_is_carried_forward_until_it_is_cleared(): void
    {
        $person = $this->participant();
        $this->expense($person, 80000);

        $forecast = app(AccountBalanceForecast::class)->between('2027-01', '2027-04');

        $this->assertSame(-30000, $forecast['2027-01'][$person->id]['balance_cents']);
        $this->assertSame(-10000, $forecast['2027-02'][$person->id]['balance_cents']);
        $this->assertSame(10000, $forecast['2027-03'][$person->id]['balance_cents']);
        $this->assertSame('2027-03', $forecast['2027-04'][$person->id]['recovered_month']);
        $report = app(SettlementCalculator::class)->calculate('2027-01');
        $this->assertSame(100000, $report['allocation_income_cents']);
        $this->assertSame(20000, $report['remaining_cents']);
        $this->assertSame([], $report['transfers']);
    }

    /** The observed month is an end balance, so its own income and costs cannot be applied a second time. */
    public function test_anchor_month_is_not_counted_again_and_new_observations_replace_the_start(): void
    {
        $person = $this->participant();
        $person->update(['account_balance_month' => '2027-01', 'account_balance' => '-0,50']);
        $this->expense($person, 100050);

        $forecast = app(AccountBalanceForecast::class)->between('2026-12', '2027-02');

        $this->assertArrayNotHasKey('2026-12', $forecast);
        $this->assertSame('-0.50', $person->fresh()->account_balance);
        $this->assertSame(-50, $forecast['2027-01'][$person->id]['balance_cents']);
        $this->assertSame(-100, $forecast['2027-02'][$person->id]['balance_cents']);
        $this->assertNull($forecast['2027-02'][$person->id]['recovered_month']);
    }

    /** Pool deposits remain on the holder's account but cannot be spent freely, and later withdrawals are counted once. */
    public function test_account_balance_and_available_money_treat_savings_separately(): void
    {
        $person = $this->participant();
        $pool = Budget::factory()->create();
        $this->expense($person, 20000, ['savings_budget_id' => $pool->id, 'interval_months' => 0]);
        $this->expense($person, 15000, ['budget_id' => $pool->id, 'start_month' => '2027-02', 'interval_months' => 0]);

        $forecast = app(AccountBalanceForecast::class)->between('2027-01', '2027-02');

        $this->assertSame(50000, $forecast['2027-01'][$person->id]['balance_cents']);
        $this->assertSame(30000, $forecast['2027-01'][$person->id]['available_cents']);
        $this->assertSame(135000, $forecast['2027-02'][$person->id]['balance_cents']);
        $this->assertSame(130000, $forecast['2027-02'][$person->id]['available_cents']);
    }

    /** The forecast includes the cost settlement once, regardless of which participant pays the bill. */
    public function test_shared_expenses_account_for_balancing_transfers_once(): void
    {
        $first = $this->participant();
        $second = Participant::create(['name' => 'Sam', 'account_balance_month' => '2026-12', 'account_balance' => '0']);
        Income::create(['name' => 'Salary', 'participant_id' => $second->id, 'amount_cents' => 100000, 'start_month' => '2027-01', 'interval_months' => 1]);
        $this->expense($first, 40000, ['split_mode' => 'equal', 'shares' => [['participant_id' => $first->id], ['participant_id' => $second->id]]]);

        $forecast = app(AccountBalanceForecast::class)->between('2027-01', '2027-01')['2027-01'];

        $this->assertSame(30000, $forecast[$first->id]['balance_cents']);
        $this->assertSame(80000, $forecast[$second->id]['balance_cents']);
    }

    /** Saved historical monthly cash flows remain authoritative when live entries or starting balances change. */
    public function test_forecast_uses_closed_reports_without_rewriting_them(): void
    {
        $person = $this->participant();
        $this->expense($person, 80000);
        $closing = app(CloseMonth::class)->close('2027-01', User::factory()->create(['is_admin' => true])->id);
        $snapshot = $closing->snapshot;
        Income::query()->update(['amount_cents' => 110000]);

        $forecast = app(AccountBalanceForecast::class)->between('2027-02', '2027-02');
        $this->assertSame(0, $forecast['2027-02'][$person->id]['balance_cents']);
        $this->assertSame('2027-02', $forecast['2027-02'][$person->id]['recovered_month']);
        $person->update(['account_balance' => '-600']);
        $this->assertSame(-10000, app(AccountBalanceForecast::class)->between('2027-02', '2027-02')['2027-02'][$person->id]['balance_cents']);
        $this->assertSame($snapshot, $closing->fresh()->snapshot);
    }

    /** Failed intermediate settlements must block later balances instead of ignoring that month's unknown movement. */
    public function test_invalid_intermediate_month_blocks_later_predictions(): void
    {
        $person = $this->participant();
        Income::query()->delete();
        $this->expense($person, 10000, ['split_mode' => 'income', 'interval_months' => 0]);

        $forecast = app(AccountBalanceForecast::class)->between('2027-01', '2027-02');

        $this->assertNull($forecast['2027-01'][$person->id]['balance_cents']);
        $this->assertNull($forecast['2027-02'][$person->id]['balance_cents']);
        $this->assertNotEmpty($forecast['2027-02'][$person->id]['error']);
    }

    /** No starting point means no invented zero balance; removing one clears the forecast. */
    public function test_unknown_balances_are_omitted(): void
    {
        $person = Participant::create(['name' => 'Unknown']);
        $this->assertSame([], app(AccountBalanceForecast::class)->between('2027-01', '2027-12'));
        $person->update(['account_balance_month' => '2027-03', 'account_balance' => '0']);
        $this->assertSame([], app(AccountBalanceForecast::class)->between('2027-01', '2027-02'));
        $person->update(['account_balance_month' => null, 'account_balance' => null]);
        $this->assertSame([], app(AccountBalanceForecast::class)->between('2027-01', '2027-12'));
    }

    /** Invalid or incomplete checkpoint values cannot be persisted through direct model writes. */
    #[DataProvider('invalidBalances')]
    public function test_invalid_reference_balances_are_rejected(array $attributes): void
    {
        $this->expectException(ValidationException::class);
        Participant::create(['name' => 'Invalid', ...$attributes]);
    }

    /** Exercise missing dates, missing amounts, signed precision and invalid reference months.
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidBalances(): array
    {
        return [
            'missing month' => [['account_balance' => '-5']],
            'missing amount' => [['account_balance_month' => '2027-01']],
            'invalid month' => [['account_balance_month' => '2027-13', 'account_balance' => '0']],
            'too precise' => [['account_balance_month' => '2027-01', 'account_balance' => '-0.001']],
        ];
    }

    /** Editing a negative checkpoint hydrates the decimal input and the forecast appears in monthly, annual and personal views. */
    public function test_participant_form_and_forecast_views(): void
    {
        $person = $this->participant();
        $this->expense($person, 80000);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Livewire::test(ManageParticipants::class)->mountAction(TestAction::make('edit')->table($person))
            ->assertSchemaStateSet(['account_balance' => '-500.00', 'account_balance_month' => '2026-12'])
            ->fillForm(['account_balance' => '-400,50'])->callMountedAction()->assertHasNoFormErrors();
        $this->assertSame(-40050, $person->fresh()->account_balance_cents);
        Livewire::test(MonthlyParticipantsTable::class, ['month' => '2027-01'])->assertSee('-200,50 €');
        $this->get(YearPlan::getUrl())->assertOk()->assertSee(__('participants.account.title'));
        Livewire::test(YearPlan::class)->set('year', '2027')->assertSee('-200,50 €')->assertSee(__('participants.account.recovered'));
        $this->get(ParticipantMonth::getUrl(['participant' => $person->id, 'month' => '2027-01']))->assertOk()->assertSee('-200,50 €');
    }

    /** Backups round-trip the signed checkpoint and restore the same cross-year forecast. */
    public function test_backup_preserves_account_balance_forecasts(): void
    {
        $person = $this->participant();
        $expected = app(AccountBalanceForecast::class)->between('2027-01', '2027-02');
        $path = tempnam(sys_get_temp_dir(), 'account-balance-');
        try {
            app(HouseholdBackup::class)->exportToZip($path);
            $person->update(['account_balance' => '100']);
            app(HouseholdBackup::class)->importFromZip($path);
            $this->assertSame($expected, app(AccountBalanceForecast::class)->between('2027-01', '2027-02'));
        } finally {
            unlink($path);
        }
    }
}
