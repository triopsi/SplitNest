<?php

namespace Tests\Feature;

use App\Filament\Resources\Participants\Pages\ManageParticipants;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use App\Models\User;
use App\Services\ArchiveParticipant;
use App\Services\CloseMonth;
use App\Services\HouseholdBackup;
use App\Services\SettlementCalculator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/** Archive boundaries retain historical identities and prevent orphaned plans or reserved cash. */
class ParticipantArchivingTest extends TestCase
{
    use RefreshDatabase;

    /** A completed schedule remains visible before the archive month but disappears from later planning. */
    public function test_archiving_preserves_previous_months_and_closed_reports(): void
    {
        $person = Participant::create(['name' => 'Departing participant']);
        $other = Participant::create(['name' => 'Active participant']);
        $admin = User::factory()->create(['is_admin' => true]);
        Income::create(['name' => 'Salary', 'participant_id' => $person->id, 'amount_cents' => 10000, 'start_month' => '2027-01', 'end_month' => '2027-01', 'interval_months' => 1]);
        $closing = app(CloseMonth::class)->close('2027-01', $admin->id);
        $snapshot = $closing->snapshot;

        app(ArchiveParticipant::class)->archive($person, '2027-02');

        $this->assertSame([$person->id, $other->id], array_column(app(SettlementCalculator::class)->calculate('2027-01')['participants'], 'id'));
        $this->assertSame([$other->id], array_column(app(SettlementCalculator::class)->calculate('2027-02')['participants'], 'id'));
        $this->assertSame($snapshot, $closing->fresh()->snapshot);
        $this->assertModelExists($person);
        $this->actingAs($admin)->get(route('closings.pdf', $closing))->assertOk();
    }

    /** Active income recipients, senders and expense shares require explicit reassignment first. */
    #[DataProvider('blockingAssignments')]
    public function test_archiving_with_future_assignments_is_rejected(string $assignment, string $message): void
    {
        $person = Participant::create(['name' => 'Participant']);
        $other = Participant::create(['name' => 'Other participant']);
        if (in_array($assignment, ['income', 'sender'], true)) {
            Income::create(['name' => 'Receipt', 'participant_id' => $assignment === 'income' ? $person->id : $other->id, 'sender_id' => $assignment === 'sender' ? $person->id : null, 'amount_cents' => 100, 'start_month' => '2027-03', 'interval_months' => 3]);
        } else {
            Expense::create(['name' => 'Bill', 'payer_id' => $assignment === 'payer' ? $person->id : $other->id, 'amount_cents' => 100, 'start_month' => '2027-03', 'interval_months' => 3, 'split_mode' => 'personal', 'shares' => [['participant_id' => $assignment === 'payer' ? $other->id : $person->id]]]);
        }

        try {
            app(ArchiveParticipant::class)->archive($person, '2027-02');
            $this->fail('Future assignment was orphaned');
        } catch (ValidationException $exception) {
            $this->assertSame(__('participants.archive.'.$message, ['name' => $person->name]), $exception->errors()['archived_from_month'][0]);
        }
        $this->assertNull($person->fresh()->archived_from_month);
    }

    /** An archive boundary must consider every future occurrence, not only bills due in that exact month.
     * @return array<string, array{string, string}>
     */
    public static function blockingAssignments(): array
    {
        return ['recipient' => ['income', 'has_incomes'], 'sender' => ['sender', 'has_incomes'], 'payer' => ['payer', 'has_expenses'], 'cost share' => ['share', 'has_expenses']];
    }

    /** Reserved cash cannot silently disappear when an account holder leaves the active report. */
    public function test_unspent_budget_reserves_prevent_archiving(): void
    {
        $person = Participant::create(['name' => 'Recipient']);
        $budget = Budget::factory()->create();
        Income::create(['name' => 'Funding', 'participant_id' => $person->id, 'budget_id' => $budget->id, 'amount_cents' => 1000, 'start_month' => '2027-01', 'interval_months' => 0]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('participants.archive.has_reserves', ['name' => $person->name]));
        app(ArchiveParticipant::class)->archive($person, '2027-02');
    }

    /** Zero balances left by a spent budget do not create phantom archived participants in the next report. */
    public function test_spent_reserves_allow_archiving_without_phantom_participants(): void
    {
        $person = Participant::create(['name' => 'Recipient']);
        $other = Participant::create(['name' => 'Active participant']);
        $budget = Budget::factory()->create();
        Income::create(['name' => 'Funding', 'participant_id' => $person->id, 'budget_id' => $budget->id, 'amount_cents' => 1000, 'start_month' => '2027-01', 'interval_months' => 0]);
        Expense::create(['name' => 'Bill', 'payer_id' => $person->id, 'budget_id' => $budget->id, 'amount_cents' => 1000, 'start_month' => '2027-01', 'interval_months' => 0, 'split_mode' => 'personal', 'shares' => [['participant_id' => $person->id]]]);

        app(ArchiveParticipant::class)->archive($person, '2027-02');

        $this->assertSame([$other->id], array_column(app(SettlementCalculator::class)->calculate('2027-02')['participants'], 'id'));
    }

    /** Archive boundaries cannot contradict a frozen report that already includes the person. */
    public function test_archiving_cannot_remove_someone_from_a_closed_month(): void
    {
        $person = Participant::create(['name' => 'Participant']);
        app(CloseMonth::class)->close('2027-03', User::factory()->create(['is_admin' => true])->id);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('participants.archive.has_closings', ['name' => $person->name]));
        app(ArchiveParticipant::class)->archive($person, '2027-02');
    }

    /** New income and cost assignments may not extend into a participant's archived period. */
    #[DataProvider('blockingAssignments')]
    public function test_archived_participants_cannot_receive_new_future_assignments(string $assignment, string $message): void
    {
        $person = Participant::create(['name' => 'Participant']);
        $other = Participant::create(['name' => 'Active participant']);
        app(ArchiveParticipant::class)->archive($person, '2027-02');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('participants.archive.assignment_invalid', ['name' => $person->name]));
        if (in_array($assignment, ['income', 'sender'], true)) {
            Income::create(['name' => 'Receipt', 'participant_id' => $assignment === 'income' ? $person->id : $other->id, 'sender_id' => $assignment === 'sender' ? $person->id : null, 'amount_cents' => 100, 'start_month' => '2027-02', 'interval_months' => 1]);
        } else {
            Expense::create(['name' => 'Bill', 'payer_id' => $assignment === 'payer' ? $person->id : $other->id, 'amount_cents' => 100, 'start_month' => '2027-02', 'interval_months' => 1, 'split_mode' => 'personal', 'shares' => [['participant_id' => $assignment === 'payer' ? $other->id : $person->id]]]);
        }
    }

    /** The participant screen provides archive, status filters and reversible reactivation. */
    public function test_archive_and_restore_are_available_in_the_participant_table(): void
    {
        $person = Participant::create(['name' => 'Participant']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageParticipants::class)->callTableAction('archive', $person, data: ['archived_from_month' => '2027-02'])->assertHasNoTableActionErrors();
        $this->assertSame('2027-02', $person->fresh()->archived_from_month);
        Livewire::test(ManageParticipants::class)->assertCanNotSeeTableRecords([$person])
            ->filterTable('archived_from_month', true)->assertCanSeeTableRecords([$person])
            ->callTableAction('restore', $person)->assertHasNoTableActionErrors();
        $this->assertNull($person->fresh()->archived_from_month);
    }

    /** Later closing emails exclude archived participants, while history is kept in earlier snapshots. */
    public function test_archived_participants_do_not_receive_later_closing_emails(): void
    {
        $person = Participant::create(['name' => 'Participant', 'email' => 'participant@example.test']);
        Participant::create(['name' => 'Active participant']);
        app(ArchiveParticipant::class)->archive($person, '2027-02');
        Mail::fake();

        app(CloseMonth::class)->close('2027-02', User::factory()->create(['is_admin' => true])->id);

        Mail::assertNothingSent();
    }

    /** Both new fields survive a full backup and can still be restored from older schema-compatible datasets. */
    public function test_backup_restores_archives_and_scheduled_prices(): void
    {
        $person = Participant::create(['name' => 'Archived participant']);
        $active = Participant::create(['name' => 'Active participant']);
        app(ArchiveParticipant::class)->archive($person, '2027-02');
        $expense = Expense::create(['name' => 'Internet', 'payer_id' => $active->id, 'amount_cents' => 4000, 'start_month' => '2027-01', 'interval_months' => 1, 'split_mode' => 'personal', 'shares' => [['participant_id' => $active->id]], 'price_changes' => [['month' => '2027-02', 'amount' => '45']]]);
        $path = tempnam(sys_get_temp_dir(), 'archive-prices-');
        try {
            app(HouseholdBackup::class)->exportToZip($path);
            $person->forceFill(['archived_from_month' => null])->save();
            $expense->update(['price_changes' => []]);

            app(HouseholdBackup::class)->importFromZip($path);

            $this->assertSame('2027-02', $person->fresh()->archived_from_month);
            $this->assertSame(4500, $expense->fresh()->amountForMonth('2027-02'));

            $zip = new ZipArchive;
            $zip->open($path);
            $manifest = json_decode($zip->getFromName('manifest.json'), true);
            foreach (['participants' => 'archived_from_month', 'expenses' => 'price_changes'] as $table => $field) {
                $rows = json_decode($zip->getFromName($table.'.json'), true);
                foreach ($rows as &$row) {
                    unset($row[$field]);
                }
                unset($row);
                $json = json_encode($rows, JSON_THROW_ON_ERROR);
                $zip->addFromString($table.'.json', $json);
                $manifest['checksums'][$table] = hash('sha256', $json);
            }
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
            $zip->close();

            app(HouseholdBackup::class)->importFromZip($path);

            $this->assertNull($person->fresh()->archived_from_month);
            $this->assertNull($expense->fresh()->price_changes);
            $this->assertSame(4000, $expense->fresh()->amountForMonth('2027-02'));
        } finally {
            unlink($path);
        }
    }

    /** Regular login accounts cannot invoke archive management through the admin screen. */
    public function test_regular_accounts_cannot_access_archive_management(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/participants')->assertForbidden();
    }
}
