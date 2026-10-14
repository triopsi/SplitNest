<?php

namespace Tests\Feature;

use App\Filament\Pages\Settlement;
use App\Models\Expense;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\ReopenMonth;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MonthReopeningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    private function closeMonthWithExpense(): MonthlyClosing
    {
        [$a, $b] = [Participant::create(['name' => 'Daniel']), Participant::create(['name' => 'Svenja'])];
        Expense::create(['name' => 'Miete', 'payer_id' => $a->id, 'amount' => '100.01', 'split_mode' => 'percentage', 'shares' => [['participant_id' => $a->id, 'percentage' => 50], ['participant_id' => $b->id, 'percentage' => 50]], 'start_month' => '2027-01', 'interval_months' => 1]);

        return app(CloseMonth::class)->close('2027-01', $this->admin()->id);
    }

    public function test_reopen_discards_the_snapshot_and_allows_reclosing(): void
    {
        $closing = $this->closeMonthWithExpense();
        $this->assertNotNull($closing->closed_at);

        $reopened = app(ReopenMonth::class)->reopen('2027-01');
        $this->assertSame($closing->id, $reopened->id);
        $this->assertNull($reopened->closed_at);
        $this->assertNull($reopened->snapshot);
        $this->assertNull($reopened->closed_by);

        Expense::firstOrFail()->update(['amount' => '200']);
        $again = app(CloseMonth::class)->close('2027-01', $this->admin()->id);
        $this->assertSame($closing->id, $again->id);
        $this->assertSame(20000, $again->snapshot['total_expense_cents']);
        $this->assertDatabaseCount('monthly_closings', 1);
    }

    public function test_reopening_a_month_that_is_not_closed_fails(): void
    {
        $this->expectException(ValidationException::class);
        app(ReopenMonth::class)->reopen('2027-01');
    }

    public function test_closed_month_stays_immutable_until_reopened(): void
    {
        $closing = $this->closeMonthWithExpense();
        try {
            $closing->update(['snapshot' => []]);
            $this->fail('Closed month was modified');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('monthly_closings', 1);
    }

    public function test_reopen_action_on_the_settlement_page(): void
    {
        $this->closeMonthWithExpense();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Settlement::class)
            ->set('month', '2027-01')
            ->mountAction('reopenMonth')
            ->assertActionMounted('reopenMonth')
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertNull(MonthlyClosing::firstOrFail()->closed_at);
    }
}
