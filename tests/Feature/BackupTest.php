<?php

namespace Tests\Feature;

use App\Filament\Pages\Backup;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\HouseholdBackup;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Backup & restore: the ZIP export/import is the migration path between
 * instances and must preserve every record and ID.
 */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Export a populated household to ZIP, wipe the tables and import it back.
     *
     * Expects all records (participants, incomes, expenses, closing snapshot)
     * to be restored unchanged and ID sequences to continue above the
     * imported IDs so new records cannot collide.
     */
    #[Group('critical')]
    public function test_export_import_roundtrip_restores_all_data(): void
    {
        $a = Participant::create(['name' => 'Max']);
        $b = Participant::create(['name' => 'Erika']);
        Income::create(['participant_id' => $a->id, 'name' => 'Gehalt', 'amount_cents' => 340000, 'start_month' => '2027-01', 'interval_months' => 1]);
        Expense::create(['name' => 'Miete', 'payer_id' => $a->id, 'amount_cents' => 10000, 'split_mode' => 'percentage', 'shares' => [['participant_id' => $a->id, 'percentage' => '50'], ['participant_id' => $b->id, 'percentage' => '50']], 'start_month' => '2027-01', 'interval_months' => 1]);
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();
        app(CloseMonth::class)->close('2027-01', $u->id);

        $path = tempnam(sys_get_temp_dir(), 'backup-test-');
        app(HouseholdBackup::class)->exportToZip($path);

        foreach (['expenses', 'incomes', 'monthly_closings', 'participants'] as $table) {
            DB::table($table)->delete();
        }
        $this->assertDatabaseCount('participants', 0);

        app(HouseholdBackup::class)->importFromZip($path);
        unlink($path);

        $this->assertDatabaseCount('participants', 2);
        $this->assertSame('Miete', Expense::firstOrFail()->name);
        $this->assertSame(340000, Income::firstOrFail()->amount_cents);
        $this->assertDatabaseHas('monthly_closings', ['month' => '2027-01']);
        $this->assertSame(10000, MonthlyClosing::firstOrFail()->snapshot['total_expense_cents']);

        // Sequences must continue above the imported IDs.
        $c = Participant::create(['name' => 'Neu']);
        $this->assertGreaterThan($b->id, $c->id);
    }

    /**
     * Upload a real export ZIP through the import action in the UI.
     *
     * Expects the extension-based upload validation to accept the file and
     * the import to replace the dataset without action errors.
     */
    #[Group('critical')]
    public function test_import_action_accepts_a_real_export_zip(): void
    {
        Participant::create(['name' => 'Max']);
        $path = tempnam(sys_get_temp_dir(), 'backup-test-');
        app(HouseholdBackup::class)->exportToZip($path);

        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Participant::create(['name' => 'Vorher']);
        Storage::disk('local')->put('backup-uploads/test.zip', file_get_contents($path));
        Livewire::test(Backup::class)
            ->mountAction('import')
            ->setActionData(['file' => ['00000000-0000-0000-0000-000000000000' => 'backup-uploads/test.zip']])
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertDatabaseCount('participants', 1);
        $this->assertSame('Max', Participant::firstOrFail()->name);
        unlink($path);
    }

    /**
     * Import a file that is not a ZIP archive.
     *
     * Expects a ValidationException mentioning the ZIP format.
     */
    public function test_import_rejects_invalid_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'backup-test-');
        file_put_contents($path, 'definitiv kein zip');
        try {
            app(HouseholdBackup::class)->importFromZip($path);
            $this->fail('Invalid backup accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ZIP', $e->getMessage());
        } finally {
            unlink($path);
        }
    }

    /**
     * Import a backup while the household data has changed since the export.
     *
     * Expects the import to fully replace the current dataset: renames are
     * reverted and additionally created records are gone.
     */
    public function test_import_replaces_existing_data_completely(): void
    {
        $a = Participant::create(['name' => 'Max']);
        $path = tempnam(sys_get_temp_dir(), 'backup-test-');
        app(HouseholdBackup::class)->exportToZip($path);

        $a->update(['name' => 'Umbenannt']);
        Participant::create(['name' => 'Zusatz']);

        app(HouseholdBackup::class)->importFromZip($path);
        unlink($path);

        $this->assertDatabaseCount('participants', 1);
        $this->assertSame('Max', Participant::firstOrFail()->name);
    }

    /**
     * Import a ZIP that contains only some of the expected table files.
     *
     * Expects a ValidationException naming the first missing table (incomes).
     */
    public function test_import_rejects_backup_with_missing_table(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'backup-test-');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('participants.json', '[]');
        $zip->close();
        try {
            app(HouseholdBackup::class)->importFromZip($path);
            $this->fail('Backup with missing table accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('incomes', $e->getMessage());
        } finally {
            unlink($path);
        }
    }
}
