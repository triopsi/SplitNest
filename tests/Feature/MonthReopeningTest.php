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
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Reopening closed months: the escape hatch when something was forgotten,
 * without giving up the immutability of closed settlements.
 */
class MonthReopeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an admin account.
     */
    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    /**
     * Close 2027-01 with a 100.01 € expense split 50/50.
     */
    private function closeMonthWithExpense(): MonthlyClosing
    {
        [$a, $b] = [Participant::create(['name' => 'Daniel']), Participant::create(['name' => 'Svenja'])];
        Expense::create(['name' => 'Miete', 'payer_id' => $a->id, 'amount' => '100.01', 'split_mode' => 'percentage', 'shares' => [['participant_id' => $a->id, 'percentage' => 50], ['participant_id' => $b->id, 'percentage' => 50]], 'start_month' => '2027-01', 'interval_months' => 1]);

        return app(CloseMonth::class)->close('2027-01', $this->admin()->id);
    }

    /**
     * Reopen a closed month, change the source data and close it again.
     *
     * Expects the same closing row to be reused: snapshot, closed_by and
     * closed_at are cleared on reopen, and the second close freezes the
     * updated numbers (20000 cents).
     */
    #[Group('critical')]
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

    /**
     * Reopen a month that was never closed.
     *
     * Expects a ValidationException ('Dieser Monat ist nicht abgeschlossen.').
     */
    public function test_reopening_a_month_that_is_not_closed_fails(): void
    {
        $this->expectException(ValidationException::class);
        app(ReopenMonth::class)->reopen('2027-01');
    }

    /**
     * Reopen a month with a malformed month string.
     *
     * Expects a ValidationException; months must follow the Y-m format.
     */
    public function test_reopen_rejects_an_invalid_month_format(): void
    {
        $this->expectException(ValidationException::class);
        app(ReopenMonth::class)->reopen('2027-13');
    }

    /**
     * Reopen a month twice in a row.
     *
     * Expects the second reopen to fail with a ValidationException, because
     * the month is already open again.
     */
    public function test_reopening_twice_fails(): void
    {
        $this->closeMonthWithExpense();
        app(ReopenMonth::class)->reopen('2027-01');
        $this->expectException(ValidationException::class);
        app(ReopenMonth::class)->reopen('2027-01');
    }

    /**
     * Try to modify a closed month, then reopen it and touch the row again.
     *
     * Expects the update while closed to throw a ValidationException, and the
     * row to be writable again after the reopen.
     */
    #[Group('critical')]
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

    /**
     * Trigger the reopen action on the settlement page for a closed month.
     *
     * Expects a confirmation dialog first and closed_at to be null afterwards.
     */
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
