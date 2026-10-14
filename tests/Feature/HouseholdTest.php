<?php

namespace Tests\Feature;

use App\Filament\Pages\ParticipantMonth;
use App\Filament\Pages\Settlement;
use App\Filament\Pages\YearPlan;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Resources\Participants\Pages\ManageParticipants;
use App\Mail\MonthClosedMail;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\SettlementCalculator;
use App\Support\Settings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * End-to-end coverage of the household domain: settlement math, schedules,
 * month closing, mail summaries, permissions and the Filament UI.
 */
class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create the two standard participants Daniel and Svenja.
     *
     * @return array{Participant, Participant}
     */
    private function people(): array
    {
        return [Participant::create(['name' => 'Daniel']), Participant::create(['name' => 'Svenja'])];
    }

    /**
     * Create a 100.01 € expense split 50/50 between two participants.
     */
    private function expense($a, $b, array $extra = []): Expense
    {
        return Expense::create(array_replace(['name' => 'Miete', 'payer_id' => $a->id, 'amount' => '100.01', 'split_mode' => 'percentage', 'shares' => [['participant_id' => $a->id, 'percentage' => 50], ['participant_id' => $b->id, 'percentage' => 50]], 'start_month' => '2027-01', 'interval_months' => 1], $extra));
    }

    /**
     * Create a monthly salary income for a participant.
     */
    private function income($p, string $amount): Income
    {
        return Income::create(['participant_id' => $p->id, 'name' => 'Gehalt', 'amount' => $amount, 'start_month' => '2027-01', 'interval_months' => 1]);
    }

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
     * Calculate a 100.01 € settlement split 50/50.
     *
     * Expects shares 5001/5000 (remainder to the lowest ID), one 50.00 €
     * transfer from Svenja to Daniel and balances that sum to exactly zero —
     * no cent may be lost.
     */
    #[Group('critical')]
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

    /**
     * Calculate a mixed settlement: income-weighted rent, an equal-split
     * expense paid by the other person and a personal cost.
     *
     * Expects exact shares (39000/35000), remaining budgets and a single
     * 250.00 € balancing transfer.
     */
    #[Group('critical')]
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

    /**
     * Calculate an income-weighted split with three participants, one of
     * them without any income.
     *
     * Expects shares [3333, 6667, 0] — zero income means zero share.
     */
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

    /**
     * Close a month whose income-weighted expense misses an income.
     *
     * Expects a ValidationException and a full rollback: no closing row
     * may be left behind.
     */
    #[Group('critical')]
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

    /**
     * Create expenses with only one 50 % share and with a duplicated
     * participant in the shares.
     *
     * Expects a ValidationException in both cases and no expense rows at all.
     */
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

    /**
     * Check a quarterly expense with start and end month against various months.
     *
     * Expects hits exactly on the interval rhythm inside the period and no
     * hits outside it; interval 0 disables the entry.
     */
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

    /**
     * Close a month, change source data afterwards and close it again.
     *
     * Expects the second close to return the same frozen row (idempotent) and
     * the snapshot to keep the original numbers; directly mutating the closed
     * row must throw a ValidationException.
     */
    #[Group('critical')]
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

    /**
     * Walk through the access rules: guest redirects, non-admin 403s, admin
     * pages and the closing PDF download.
     *
     * Expects guests to be redirected to the login, non-admins to be
     * forbidden, admins to see all pages and the PDF to start with '%PDF-'.
     */
    #[Group('critical')]
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

    /**
     * Create and edit an expense through the Filament form, using German
     * decimal input.
     *
     * Expects '1701,25' to be stored as 170125 cents and an edit to '1800,00'
     * to update the amount to 180000 cents.
     */
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

    /**
     * Run the splitnest:user Artisan command with a hidden password prompt.
     *
     * Expects an admin account whose password is stored hashed.
     */
    #[Group('critical')]
    public function test_terminal_command_creates_admin_with_hashed_password(): void
    {
        $this->artisan('splitnest:user', ['email' => 'daniel@example.test', '--name' => 'Daniel'])
            ->expectsQuestion('Passwort (mindestens 12 Zeichen)', 'A-long-test-password!')
            ->expectsQuestion('Passwort wiederholen', 'A-long-test-password!')->assertSuccessful();
        $u = User::where('email', 'daniel@example.test')->firstOrFail();
        $this->assertTrue($u->is_admin);
        $this->assertTrue(Hash::check('A-long-test-password!', $u->password));
    }

    /**
     * Trigger the close-month action on the settlement page.
     *
     * Expects a confirmation dialog first and a persisted closing row for the
     * selected month afterwards.
     */
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

    /**
     * Look for the delete action on the participant table.
     *
     * Expects no delete action to exist, because participants with linked
     * records must never be removed via the UI.
     */
    public function test_participants_cannot_be_deleted_via_ui(): void
    {
        [$a] = $this->people();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageParticipants::class)
            ->assertActionDoesNotExist(TestAction::make('delete')->table($a));
    }

    /**
     * Open the year planning page without parameters.
     *
     * Expects the current year to be preselected.
     */
    public function test_year_plan_defaults_to_current_year(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(YearPlan::class)->assertSet('year', now()->format('Y'));
    }

    /**
     * Change the month and year dropdowns on the settlement page.
     *
     * Expects the canonical month property to follow the dropdowns ('2027-03')
     * and to default to the current month on first load.
     */
    public function test_month_dropdowns_update_the_settlement_month(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Settlement::class)
            ->assertSet('month', now()->format('Y-m'))
            ->assertSet('yearPicker', now()->format('Y'))
            ->assertSet('monthPicker', (string) (int) now()->format('m'))
            ->set('yearPicker', '2027')
            ->set('monthPicker', '3')
            ->assertSet('month', '2027-03');
    }

    /**
     * Open the participant detail page for Svenja with shared and personal costs.
     *
     * Expects her shared costs, her personal cost and her income to be shown —
     * and Daniel's personal cost to be hidden.
     */
    public function test_participant_month_page_shows_only_their_costs(): void
    {
        [$a, $b] = $this->people();
        $this->expense($a, $b, ['name' => 'Miete', 'amount' => '100']);
        $this->expense($a, $b, ['name' => 'Svenjas Handy', 'split_mode' => 'personal', 'shares' => [['participant_id' => $b->id]]]);
        $this->expense($a, $b, ['name' => 'Daniels Server', 'split_mode' => 'personal', 'shares' => [['participant_id' => $a->id]]]);
        $this->income($b, '2800');
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ParticipantMonth::class, ['participant' => $b, 'month' => '2027-01'])
            ->assertSee('Miete')
            ->assertSee('Svenjas Handy')
            ->assertSee('Gehalt')
            ->assertDontSee('Daniels Server');
    }

    /**
     * Render the panel with a fixed app version.
     *
     * Expects the footer to display 'v9.9.9'.
     */
    public function test_footer_shows_the_app_version(): void
    {
        config(['app.version' => '9.9.9']);
        $this->actingAs($this->admin());
        $this->get('/admin')->assertSee('v9.9.9', false);
    }

    /**
     * Close a month with two participants that have e-mail addresses.
     *
     * Expects one summary mail per participant, each with the PDF attached and
     * the personal transfer list (or 'Keine — du bist ausgeglichen.') in the text.
     */
    public function test_closing_a_month_sends_summary_mails_with_pdf(): void
    {
        Mail::fake();
        $a = Participant::create(['name' => 'Daniel', 'email' => 'daniel@example.test']);
        $b = Participant::create(['name' => 'Svenja', 'email' => 'svenja@example.test']);
        $this->expense($a, $b);
        app(CloseMonth::class)->close('2027-01', $this->admin()->id);

        Mail::assertSent(MonthClosedMail::class, 2);
        Mail::assertSent(MonthClosedMail::class, function (MonthClosedMail $mail) {
            if (! $mail->hasTo('svenja@example.test')) {
                return false;
            }
            $this->assertCount(1, $mail->attachments());
            $mail->assertSeeInText('Hallo Svenja');
            $mail->assertSeeInText('50,00 € an Daniel');

            return true;
        });
        Mail::assertSent(MonthClosedMail::class, function (MonthClosedMail $mail) {
            if (! $mail->hasTo('daniel@example.test')) {
                return false;
            }
            $mail->assertSeeInText('Keine — du bist ausgeglichen.');

            return true;
        });
    }

    /**
     * Configure a custom closing mail text with placeholders and close a month.
     *
     * Expects the mail body to contain the replaced values (name, localized
     * month, transfer list).
     */
    public function test_closing_mail_text_is_configurable_with_placeholders(): void
    {
        Mail::fake();
        Settings::set('mail_body', 'Hi {name}! Monat: {monat}. Zahlungen: {ueberweisungen}');
        $a = Participant::create(['name' => 'Daniel', 'email' => 'daniel@example.test']);
        $b = Participant::create(['name' => 'Svenja', 'email' => 'svenja@example.test']);
        $this->expense($a, $b);
        app(CloseMonth::class)->close('2027-01', $this->admin()->id);

        Mail::assertSent(MonthClosedMail::class, function (MonthClosedMail $mail) {
            if (! $mail->hasTo('svenja@example.test')) {
                return false;
            }
            $mail->assertSeeInText('Hi Svenja! Monat: Januar 2027. Zahlungen: - 50,00 € an Daniel');

            return true;
        });
    }

    /**
     * Delete a participant directly via the model.
     *
     * Expects a ValidationException; the record must stay in the database.
     */
    public function test_participant_cannot_be_deleted(): void
    {
        [$a] = $this->people();
        try {
            $a->delete();
            $this->fail('Participant deletion allowed');
        } catch (ValidationException) {
        }
        $this->assertDatabaseHas('participants', ['id' => $a->id]);
    }

    /**
     * Delete a closed monthly closing directly via the model.
     *
     * Expects a ValidationException; the closing must stay in the database so
     * the frozen PDF data remains available.
     */
    #[Group('critical')]
    public function test_monthly_closing_cannot_be_deleted(): void
    {
        [$a, $b] = $this->people();
        $this->expense($a, $b);
        $closing = app(CloseMonth::class)->close('2027-01', $this->admin()->id);
        try {
            $closing->delete();
            $this->fail('Closing deletion allowed');
        } catch (ValidationException) {
        }
        $this->assertDatabaseHas('monthly_closings', ['id' => $closing->id]);
    }

    /**
     * Calculate a settlement for the invalid month '2027-13'.
     *
     * Expects a ValidationException; months must follow the Y-m format.
     */
    public function test_calculate_rejects_invalid_month_format(): void
    {
        $this->people();
        $this->expectException(ValidationException::class);
        app(SettlementCalculator::class)->calculate('2027-13');
    }
}
