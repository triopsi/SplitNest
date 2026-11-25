<?php

namespace Tests\Feature;

use App\Filament\Pages\SavingsOverview;
use App\Models\Budget;
use App\Models\Income;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Verify accumulated reserves, historical snapshots and explanatory navigation. */
class SavingsOverviewTest extends TestCase
{
    use RefreshDatabase;

    /** Set up an administrator and the same panel context used by the overview route. */
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    /** Accumulated balances include prior months and preserve separate account holders. */
    public function test_overview_shows_accumulated_funds_and_account_holders(): void
    {
        $first = Participant::create(['name' => 'Alex']);
        $second = Participant::create(['name' => 'Sam']);
        $budget = Budget::factory()->create(['name' => 'Holiday pot']);
        foreach ([[$first, 150], [$second, 250]] as [$person, $cents]) {
            Income::create(['name' => 'Monthly saving', 'participant_id' => $person->id, 'budget_id' => $budget->id, 'amount_cents' => $cents, 'start_month' => '2027-01', 'interval_months' => 1]);
        }

        $page = Livewire::test(SavingsOverview::class)->set('month', '2027-02');
        $page->assertViewHas('total', 800)
            ->assertSee('Holiday pot')->assertSee('8,00 €')->assertSee('3,00 €')->assertSee('5,00 €')
            ->assertSee('Alex')->assertSee('Sam')->assertSee(__('funding.savings.planned'))
            ->assertSee(__('funding.savings.what'));
        $this->get(SavingsOverview::getUrl(['month' => '2027-02']))->assertOk()->assertSee('8,00 €');
    }

    /** Frozen balances and names do not change when current source records are edited. */
    public function test_closed_month_uses_saved_balances_and_names(): void
    {
        $person = Participant::create(['name' => 'Original holder']);
        $budget = Budget::factory()->create(['name' => 'Original pot']);
        $income = Income::create(['name' => 'Monthly saving', 'participant_id' => $person->id, 'budget_id' => $budget->id, 'amount_cents' => 400, 'start_month' => '2027-01', 'interval_months' => 1]);
        app(CloseMonth::class)->close('2027-01', auth()->id());
        $budget->update(['name' => 'Renamed pot']);
        $person->update(['name' => 'Renamed holder']);
        $income->update(['amount_cents' => 900]);

        Livewire::test(SavingsOverview::class)->set('month', '2027-01')
            ->assertViewHas('total', 400)->assertSee('Original pot')->assertSee('Original holder')
            ->assertDontSee('Renamed pot')->assertDontSee('Renamed holder')
            ->assertSee(__('funding.savings.closed'));
    }

    /** Empty installations and invalid month inputs display useful feedback without a calculation error. */
    public function test_empty_overview_and_invalid_month_are_handled(): void
    {
        Livewire::test(SavingsOverview::class)->assertViewHas('total', 0)->assertSee(__('funding.no_budgets'))
            ->set('month', '2027-13')->assertSee(__('funding.savings.invalid_month'));
    }
}
