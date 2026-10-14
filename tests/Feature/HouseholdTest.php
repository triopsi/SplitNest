<?php

namespace Tests\Feature;

use App\Filament\Pages\Settlement;
use App\Filament\Pages\YearPlan;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Resources\Participants\Pages\ManageParticipants;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\SettlementCalculator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    private function people(): array
    {
        return [Participant::create(['name' => 'Daniel']), Participant::create(['name' => 'Svenja'])];
    }

    private function expense($a, $b, array $extra = []): Expense
    {
        return Expense::create(array_replace(['name' => 'Miete', 'payer_id' => $a->id, 'amount' => '100.01', 'split_mode' => 'percentage', 'shares' => [['participant_id' => $a->id, 'percentage' => 50], ['participant_id' => $b->id, 'percentage' => 50]], 'start_month' => '2027-01', 'interval_months' => 1], $extra));
    }

    private function income($p, string $amount): Income
    {
        return Income::create(['participant_id' => $p->id, 'name' => 'Gehalt', 'amount' => $amount, 'start_month' => '2027-01', 'interval_months' => 1]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    public function test_cents_and_transfer_are_conserved(): void
    {
        [$a,$b] = $this->people();
        $this->expense($a, $b);
        $r = app(SettlementCalculator::class)->calculate('2027-01');
        $this->assertSame([5001, 5000], array_column($r['participants'], 'share_cents'));
        $this->assertSame(5000, $r['transfers'][0]['amount_cents']);
        $this->assertSame($b->id, $r['transfers'][0]['from_id']);
        $this->assertSame(0, array_sum(array_column($r['participants'], 'balance_cents')));
    }

    public function test_income_split_personal_costs_and_multiple_payers(): void
    {
        [$a,$b] = $this->people();
        $this->income($a, '3400');
        $this->income($b, '2800');
        $this->expense($a, $b, ['amount' => '620', 'split_mode' => 'income']);
        $this->expense($a, $b, ['name' => 'Svenjas Einkauf', 'payer_id' => $b->id, 'amount' => '100', 'split_mode' => 'equal']);
        $this->expense($a, $b, ['name' => 'Persönlich', 'amount' => '20', 'split_mode' => 'personal', 'shares' => [['participant_id' => $b->id]]]);
        $r = app(SettlementCalculator::class)->calculate('2027-01');
        $this->assertSame([39000, 35000], array_column($r['participants'], 'share_cents'));
        $this->assertSame([301000, 245000], array_column($r['participants'], 'remaining_cents'));
        $this->assertSame(25000, $r['transfers'][0]['amount_cents']);
    }

    public function test_three_participants_and_zero_income(): void
    {
        [$a,$b] = $this->people();
        $c = Participant::create(['name' => 'Gast']);
        $this->income($a, '1000');
        $this->income($b, '2000');
        $this->expense($a, $b, ['amount' => '100', 'split_mode' => 'income', 'shares' => [['participant_id' => $a->id], ['participant_id' => $b->id], ['participant_id' => $c->id]]]);
        $r = app(SettlementCalculator::class)->calculate('2027-01');
        $this->assertSame([3333, 6667, 0], array_column($r['participants'], 'share_cents'));
    }

    public function test_missing_income_prevents_closing_and_rolls_back(): void
    {
        [$a,$b] = $this->people();
        $this->expense($a, $b, ['split_mode' => 'income']);
        $u = $this->admin();
        try {
            app(CloseMonth::class)->close('2027-01', $u->id);
            $this->fail('Missing income accepted');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('monthly_closings', 0);
    }

    public function test_invalid_percentages_and_duplicate_participants_are_rejected(): void
    {
        [$a,$b] = $this->people();
        foreach ([[['participant_id' => $a->id, 'percentage' => 50]], [['participant_id' => $a->id, 'percentage' => 50], ['participant_id' => $a->id, 'percentage' => 50]]] as $shares) {
            try {
                $this->expense($a, $b, ['shares' => $shares]);
                $this->fail('Invalid shares accepted');
            } catch (ValidationException) {
            }
        }
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_schedule_honors_intervals_and_boundaries(): void
    {
        [$a,$b] = $this->people();
        $e = $this->expense($a, $b, ['interval_months' => 3, 'start_month' => '2026-11', 'end_month' => '2027-05']);
        foreach (['2026-11', '2027-02', '2027-05'] as $m) {
            $this->assertTrue($e->appliesTo($m));
        }
        foreach (['2026-10', '2027-01', '2027-08'] as $m) {
            $this->assertFalse($e->appliesTo($m));
        }
        $e->interval_months = 0;
        $e->save();
        $this->assertFalse($e->appliesTo('2027-02'));
    }

    public function test_closing_is_idempotent_and_freezes_pdf_data(): void
    {
        [$a,$b] = $this->people();
        $e = $this->expense($a, $b);
        $u = $this->admin();
        $c = app(CloseMonth::class)->close('2027-01', $u->id);
        $e->update(['amount' => '200']);
        $a->update(['name' => 'Umbenannt']);
        $again = app(CloseMonth::class)->close('2027-01', $u->id);
        $this->assertSame($c->id, $again->id);
        $this->assertSame(10001, $again->snapshot['total_expense_cents']);
        $this->assertSame('Daniel', $again->snapshot['participants'][0]['name']);
        $this->assertDatabaseCount('monthly_closings', 1);
        $this->expectException(ValidationException::class);
        $c->update(['snapshot' => []]);
    }

    public function test_login_admin_pages_and_protected_pdf(): void
    {
        [$a,$b] = $this->people();
        $this->expense($a, $b);
        $admin = $this->admin();
        $closing = app(CloseMonth::class)->close('2027-01', $admin->id);
        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
        $this->get('/closings/'.$closing->id.'/pdf')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->get('/closings/'.$closing->id.'/pdf')->assertForbidden();
        $this->actingAs($admin);
        foreach (['/admin?month=2027-01', '/admin/participants', '/admin/incomes', '/admin/expenses', '/admin/year-plan'] as $url) {
            $this->get($url)->assertOk();
        }
        $pdf = $this->get('/closings/'.$closing->id.'/pdf')->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_expense_form_creates_and_edits_decimal_amounts_and_periods(): void
    {
        [$a,$b] = $this->people();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageExpenses::class)
            ->callAction('create', data: ['name' => 'Miete', 'payer_id' => $a->id, 'amount' => '1701,25', 'split_mode' => 'percentage', 'shares' => [['participant_id' => $a->id, 'percentage' => '50'], ['participant_id' => $b->id, 'percentage' => '50']], 'start_month' => '2027-01', 'end_month' => '2027-12', 'interval_months' => 1])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('expenses', ['name' => 'Miete', 'amount_cents' => 170125, 'end_month' => '2027-12']);
        $e = Expense::firstOrFail();
        Livewire::test(ManageExpenses::class)
            ->callAction(TestAction::make('edit')->table($e), data: ['amount' => '1800,00'])
            ->assertHasNoActionErrors();
        $this->assertSame(180000, $e->fresh()->amount_cents);
    }

    public function test_terminal_command_creates_admin_with_hashed_password(): void
    {
        $this->artisan('splitnest:user', ['email' => 'daniel@example.test', '--name' => 'Daniel'])
            ->expectsQuestion('Passwort (mindestens 12 Zeichen)', 'A-long-test-password!')
            ->expectsQuestion('Passwort wiederholen', 'A-long-test-password!')->assertSuccessful();
        $u = User::where('email', 'daniel@example.test')->firstOrFail();
        $this->assertTrue($u->is_admin);
        $this->assertTrue(Hash::check('A-long-test-password!', $u->password));
    }

    public function test_month_closing_action_opens_confirmation_and_saves(): void
    {
        [$a, $b] = $this->people();
        $this->expense($a, $b);
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Settlement::class)
            ->set('month', '2027-03')
            ->mountAction('closeMonth')
            ->assertActionMounted('closeMonth')
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('monthly_closings', ['month' => '2027-03']);
    }

    public function test_participants_cannot_be_deleted_via_ui(): void
    {
        [$a] = $this->people();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageParticipants::class)
            ->assertActionDoesNotExist(TestAction::make('delete')->table($a));
    }

    public function test_year_plan_defaults_to_current_year(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(YearPlan::class)->assertSet('year', now()->format('Y'));
    }
}
