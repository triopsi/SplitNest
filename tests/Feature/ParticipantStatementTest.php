<?php

namespace Tests\Feature;

use App\Filament\Pages\ParticipantMonth;
use App\Filament\Resources\Participants\Pages\ManageParticipants;
use App\Mail\MonthClosedMail;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\ClosingReportPdf;
use App\Services\HouseholdBackup;
use App\Services\ParticipantStatement;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/** Recipient-level privacy for frozen statements, outgoing attachments and administrator previews. */
class ParticipantStatementTest extends TestCase
{
    use RefreshDatabase;

    /** Create a restricted contributor, private household expenses and both kinds of own obligations.
     * @return array{Participant, Participant, Participant, MonthlyClosing}
     */
    private function closing(): array
    {
        $max = Participant::create(['name' => 'Max', 'email' => 'max@example.test']);
        $erika = Participant::create(['name' => 'Erika', 'email' => 'erika@example.test']);
        $robin = Participant::create(['name' => 'Robin', 'email' => 'robin@example.test', 'only_own_report' => true]);
        foreach ([$max, $erika] as $participant) {
            Income::create(['name' => 'Confidential salary', 'participant_id' => $participant->id, 'amount_cents' => 432187, 'start_month' => '2027-01', 'interval_months' => 1]);
        }
        $budget = Budget::factory()->create(['name' => 'Private child savings']);
        Income::create(['name' => 'Private child benefit', 'participant_id' => $max->id, 'budget_id' => $budget->id, 'amount_cents' => 25900, 'start_month' => '2027-01', 'interval_months' => 1]);
        Expense::create([
            'name' => 'Private mortgage', 'payer_id' => $max->id, 'amount_cents' => 123456, 'split_mode' => 'equal',
            'shares' => [['participant_id' => $max->id], ['participant_id' => $erika->id]], 'start_month' => '2027-01', 'interval_months' => 1,
        ]);
        $netflix = Expense::create([
            'name' => 'Netflix', 'payer_id' => $max->id, 'amount_cents' => 2000, 'split_mode' => 'equal',
            'shares' => [['participant_id' => $max->id], ['participant_id' => $erika->id]], 'start_month' => '2027-01', 'interval_months' => 1,
        ]);
        Income::create([
            'name' => 'Netflix contribution', 'participant_id' => $max->id, 'sender_id' => $robin->id,
            'expense_id' => $netflix->id, 'include_in_payment_plan' => true, 'amount_cents' => 499,
            'start_month' => '2027-01', 'interval_months' => 1,
        ]);
        Expense::create([
            'name' => 'Shared music', 'payer_id' => $max->id, 'amount_cents' => 1000, 'split_mode' => 'equal',
            'shares' => [['participant_id' => $max->id], ['participant_id' => $robin->id]], 'start_month' => '2027-01', 'interval_months' => 1,
        ]);
        $closing = app(CloseMonth::class)->close('2027-01', User::factory()->create(['is_admin' => true])->id);

        return [$max, $erika, $robin, $closing];
    }

    /** A minimized statement exposes only personal shares and forwarding contributions, never household data. */
    public function test_personal_statement_contains_only_own_amounts_and_direct_payments(): void
    {
        Mail::fake();
        [$max, , $robin, $closing] = $this->closing();

        $statement = app(ParticipantStatement::class)->forParticipant($closing, $robin);

        $this->assertSame('Robin', $statement['name']);
        $this->assertSame([
            ['name' => 'Shared music', 'kind' => 'share', 'amount_cents' => 500],
            ['name' => 'Netflix contribution', 'kind' => 'contribution', 'amount_cents' => 499],
        ], $statement['costs']);
        $this->assertSame(999, $statement['outgoing_cents']);
        $this->assertSame(0, $statement['incoming_cents']);
        $this->assertCount(1, $statement['payments']);
        $this->assertSame('Max', $statement['payments'][0]['to_name']);
        $this->assertSame(['month', 'saved_on', 'name', 'costs', 'payments', 'outgoing_cents', 'incoming_cents'], array_keys($statement));
        $html = view('pdf.participant-closing', compact('statement'))->render();
        $this->assertStringContainsString('9,99 €', $html);
        $this->assertStringContainsString('5,00 €', $html);
        $this->assertStringContainsString('4,99 €', $html);
        foreach (['Confidential salary', 'Private mortgage', 'Private child savings', 'Private child benefit', 'Erika', '4.321,87', '1.234,56', '20,00', '10,00'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        Mail::assertSent(MonthClosedMail::class, 3);
    }

    /** The real listener requests per-recipient rendering and attaches each permitted PDF to its own mail. */
    public function test_restricted_mail_receives_its_own_pdf_while_other_recipients_keep_the_full_report(): void
    {
        Mail::fake();
        $wrapper = Mockery::mock(PdfDocument::class);
        $fullDocument = Mockery::mock(PdfDocument::class);
        $personalDocument = Mockery::mock(PdfDocument::class);
        $wrapper->shouldReceive('loadView')->once()->with('pdf.closing', Mockery::type('array'))->andReturn($fullDocument);
        $wrapper->shouldReceive('loadView')->once()->with('pdf.participant-closing', Mockery::on(function (array $data): bool {
            $this->assertSame(['statement'], array_keys($data));
            $this->assertSame('Robin', $data['statement']['name']);
            $this->assertSame(999, $data['statement']['outgoing_cents']);
            $this->assertStringNotContainsString('Confidential salary', json_encode($data));
            $this->assertStringNotContainsString('Private mortgage', json_encode($data));

            return true;
        }))->andReturn($personalDocument);
        foreach ([$fullDocument, $personalDocument] as $document) {
            $document->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
            $document->shouldReceive('setOption')->once()->with('isRemoteEnabled', false)->andReturnSelf();
        }
        $fullDocument->shouldReceive('output')->once()->andReturn('full-household-pdf');
        $personalDocument->shouldReceive('output')->once()->andReturn('robin-personal-pdf');
        Pdf::swap($wrapper);

        $this->closing();

        Mail::assertSent(MonthClosedMail::class, 3);
        Mail::assertSent(MonthClosedMail::class, function (MonthClosedMail $mail): bool {
            if (! $mail->hasTo('robin@example.test')) {
                return false;
            }
            $this->assertSame('robin-personal-pdf', $mail->pdfData);
            $this->assertCount(1, $mail->attachments());
            $mail->assertSeeInText('9,99 € to Max');
            $mail->assertDontSeeInText('Erika');
            $mail->assertDontSeeInHtml('Private mortgage');

            return true;
        });
        Mail::assertSent(MonthClosedMail::class, fn (MonthClosedMail $mail): bool => $mail->hasTo('max@example.test') && $mail->pdfData === 'full-household-pdf');
    }

    /** The administrator preview and renderer enforce the current privacy setting on a frozen month. */
    public function test_preview_uses_the_same_restricted_renderer_and_frozen_amounts(): void
    {
        Mail::fake();
        [, , $robin, $closing] = $this->closing();
        Expense::where('name', 'Shared music')->firstOrFail()->update(['amount_cents' => 100000]);
        $robin->update(['name' => 'Updated name']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $statement = app(ParticipantStatement::class)->forParticipant($closing, $robin);
        $pdf = app(ClosingReportPdf::class)->render($closing, $robin);

        $this->assertSame('Robin', $statement['name']);
        $this->assertSame(999, $statement['outgoing_cents']);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->get(route('closings.participant.pdf', ['closing' => $closing, 'participant' => $robin]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(ParticipantMonth::getUrl(['participant' => $robin->id, 'month' => '2027-01']))->assertOk()->assertSee(__('participants.detail.recipient_pdf'));
        Mail::assertSent(MonthClosedMail::class, 3);
    }

    /** A restricted recipient with no obligations receives an empty personal statement, never a full fallback. */
    public function test_participant_without_costs_or_legacy_membership_receives_no_household_data(): void
    {
        $person = Participant::create(['name' => 'New contributor', 'only_own_report' => true]);
        $closing = MonthlyClosing::create([
            'month' => '2027-01', 'closed_at' => now(), 'snapshot' => [
                'version' => 1, 'participants' => [['id' => 999, 'name' => 'Private person']],
                'expenses' => [], 'transfers' => [], 'incomes' => [['name' => 'Secret salary']],
            ],
        ]);

        $statement = app(ParticipantStatement::class)->forParticipant($closing, $person);
        $html = view('pdf.participant-closing', compact('statement'))->render();

        $this->assertSame([], $statement['costs']);
        $this->assertSame([], $statement['payments']);
        $this->assertSame(0, $statement['outgoing_cents']);
        $this->assertStringContainsString(__('pdf.personal.no_costs'), $html);
        $this->assertStringNotContainsString('Secret salary', $html);
        $this->assertStringNotContainsString('Private person', $html);
    }

    /** Incoming transfers involving the recipient are retained; unrelated payments are omitted. */
    public function test_personal_statement_includes_incoming_payments_and_supports_legacy_transfers(): void
    {
        $person = Participant::create(['name' => 'Recipient', 'only_own_report' => true]);
        $closing = MonthlyClosing::create(['month' => '2027-01', 'closed_at' => now(), 'snapshot' => [
            'participants' => [['id' => $person->id, 'name' => 'Frozen recipient']], 'expenses' => [],
            'transfers' => [
                ['from_id' => 100, 'from_name' => 'Payer', 'to_id' => $person->id, 'to_name' => 'Frozen recipient', 'amount_cents' => 499],
                ['from_id' => 101, 'from_name' => 'Secret payer', 'to_id' => 102, 'to_name' => 'Secret recipient', 'amount_cents' => 99999],
            ],
        ]]);

        $statement = app(ParticipantStatement::class)->forParticipant($closing, $person);

        $this->assertSame(0, $statement['outgoing_cents']);
        $this->assertSame(499, $statement['incoming_cents']);
        $this->assertCount(1, $statement['payments']);
        $this->assertFalse($statement['payments'][0]['outgoing']);
        $this->assertSame('Payer', $statement['payments'][0]['from_name']);
    }

    /** Only administrators can inspect recipient previews; guests and ordinary accounts are refused. */
    public function test_preview_download_requires_administrator_access(): void
    {
        $person = Participant::create(['name' => 'Robin', 'only_own_report' => true]);
        $closing = MonthlyClosing::create(['month' => '2027-01', 'closed_at' => now(), 'snapshot' => ['participants' => [], 'expenses' => [], 'transfers' => []]]);
        $url = route('closings.participant.pdf', ['closing' => $closing, 'participant' => $person]);

        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $this->get(route('closings.pdf', $closing))->assertForbidden();
    }

    /** Open months and missing participant bindings do not reveal a report. */
    public function test_preview_refuses_open_months_and_unknown_participants(): void
    {
        $person = Participant::create(['name' => 'Robin', 'only_own_report' => true]);
        $closing = MonthlyClosing::create(['month' => '2027-01']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('closings.participant.pdf', ['closing' => $closing, 'participant' => $person]))->assertNotFound();
        $this->get(route('closings.participant.pdf', ['closing' => $closing, 'participant' => 99999]))->assertNotFound();
    }

    /** The participant edit form persists the visibility preference and shows it in the list. */
    public function test_participant_form_can_enable_and_disable_the_personal_statement(): void
    {
        $person = Participant::create(['name' => 'Robin']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageParticipants::class)->callTableAction('edit', $person, data: ['name' => 'Robin', 'only_own_report' => true])
            ->assertHasNoTableActionErrors()->assertTableColumnStateSet('only_own_report', true, $person);

        $this->assertTrue($person->fresh()->only_own_report);
        Livewire::test(ManageParticipants::class)->callTableAction('edit', $person, data: ['name' => 'Robin', 'only_own_report' => false])->assertHasNoTableActionErrors();
        $this->assertFalse($person->fresh()->only_own_report);
    }

    /** Backup export and restore preserve each recipient's visibility preference. */
    public function test_backups_preserve_the_personal_statement_preference(): void
    {
        $person = Participant::create(['name' => 'Robin', 'only_own_report' => true]);
        $path = tempnam(sys_get_temp_dir(), 'personal-statement-');
        try {
            app(HouseholdBackup::class)->exportToZip($path);
            app(HouseholdBackup::class)->importFromZip($path);

            $this->assertTrue(Participant::findOrFail($person->id)->only_own_report);
        } finally {
            unlink($path);
        }
    }

    /** Participant and item names are escaped in the restricted PDF template. */
    public function test_personal_statement_escapes_names_and_cost_labels(): void
    {
        $person = Participant::create(['name' => '<script>alert("name")</script>', 'only_own_report' => true]);
        $closing = MonthlyClosing::create(['month' => '2027-01', 'closed_at' => now(), 'snapshot' => [
            'participants' => [['id' => $person->id, 'name' => $person->name]], 'transfers' => [],
            'expenses' => [['name' => '<script>alert("item")</script>', 'allocations' => [$person->id => 499]]],
        ]]);

        $statement = app(ParticipantStatement::class)->forParticipant($closing, $person);
        $html = view('pdf.participant-closing', compact('statement'))->render();

        $this->assertStringContainsString(e($person->name), $html);
        $this->assertStringNotContainsString($person->name, $html);
        $this->assertStringContainsString(e('<script>alert("item")</script>'), $html);
        $this->assertStringNotContainsString('<script>alert("item")</script>', $html);
    }
}
