<?php

namespace Tests\Feature;

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Resources\Incomes\Pages\ManageIncomes;
use App\Mail\MonthClosedMail;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\ParticipantStatement;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Calendar months stay readable and localized without changing stored schedule values. */
class MonthDisplayTest extends TestCase
{
    use RefreshDatabase;

    /** Both schedule tables format start/end months and calculate February correctly on the 31st. */
    #[DataProvider('localizedMonths')]
    public function test_schedule_columns_display_localized_names(string $locale, string $february, string $march, string $october): void
    {
        app()->setLocale($locale);
        $this->travelTo('2026-10-31 12:00:00');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $person = Participant::create(['name' => 'Recipient']);
        $income = Income::create(['name' => 'Salary', 'participant_id' => $person->id, 'amount_cents' => 100000, 'start_month' => '2026-02', 'end_month' => '2027-03', 'interval_months' => 1]);
        $expense = Expense::create(['name' => 'Insurance', 'payer_id' => $person->id, 'amount_cents' => 10000, 'start_month' => '2026-02', 'end_month' => '2027-03', 'interval_months' => 6, 'split_mode' => 'personal', 'shares' => [['participant_id' => $person->id]]]);

        Livewire::test(ManageExpenses::class)
            ->assertTableColumnFormattedStateSet('start_month', $february.' 2026', $expense)
            ->assertTableColumnFormattedStateSet('end_month', $march.' 2027', $expense)
            ->assertTableColumnStateSet('next_billing', $february.' 2027', $expense);
        Livewire::test(ManageIncomes::class)
            ->assertTableColumnFormattedStateSet('start_month', $february.' 2026', $income)
            ->assertTableColumnFormattedStateSet('end_month', $march.' 2027', $income);

        $this->assertSame('2026-02', $expense->fresh()->start_month);
        $this->assertSame('2027-03', $income->fresh()->end_month);
    }

    /** Dashboard, year plan, participant page and both PDF/mail templates use the selected language. */
    #[DataProvider('localizedMonths')]
    public function test_reports_and_mail_display_the_same_localized_month(string $locale, string $february, string $march, string $october): void
    {
        app()->setLocale($locale);
        $this->travelTo('2026-10-31 12:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $person = Participant::create(['name' => 'Recipient', 'only_own_report' => true]);
        Mail::fake();
        $closing = app(CloseMonth::class)->close('2026-10', $admin->id);
        $this->actingAs($admin);
        $label = $october.' 2026';

        $this->get('/admin?month=2026-10')->assertOk();
        $this->get('/admin/year-plan?year=2026')->assertOk()->assertSee($label);
        $this->get('/admin/participants/'.$person->id.'/2026-10')->assertOk()->assertSee($label);
        $this->assertStringContainsString($label, view('pdf.closing', ['closing' => $closing, 'report' => $closing->snapshot])->render());
        $statement = app(ParticipantStatement::class)->forParticipant($closing, $person);
        $this->assertStringContainsString($label, view('pdf.participant-closing', ['statement' => $statement])->render());
        $mail = new MonthClosedMail($closing, 'Statement', 'pdf-data');
        $this->assertStringContainsString($label, $mail->envelope()->subject);
        $mail->assertSeeInHtml($label);
        Mail::assertNothingSent();
    }

    /** Ship full month names with German umlauts and English names as explicit expectations.
     * @return array<string, array{string, string, string, string}>
     */
    public static function localizedMonths(): array
    {
        return ['german' => ['de', 'Februar', 'März', 'Oktober'], 'english' => ['en', 'February', 'March', 'October']];
    }
}
