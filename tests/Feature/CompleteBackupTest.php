<?php

namespace Tests\Feature;

use App\Filament\Pages\Backup;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\HouseholdInvitation;
use App\Models\Income;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\HouseholdBackup;
use App\Support\MonthlyDashboardLayout;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;
use ZipArchive;

/** Complete recovery, archive integrity, account portability and legacy household restoration. */
class CompleteBackupTest extends TestCase
{
    use RefreshDatabase;

    /** Populate every durable dataset, including links to non-default user and participant IDs.
     * @return array{User, Participant}
     */
    private function source(): array
    {
        $user = User::factory()->create(['id' => 42, 'is_admin' => true, 'locale' => 'de', 'password' => Hash::make('RecoveryPassword123!'), 'monthly_dashboard_layout' => MonthlyDashboardLayout::defaults()]);
        Passkey::factory()->create(['authenticatable_id' => $user->id]);
        HouseholdInvitation::factory()->create(['invited_by' => $user->id]);
        Settings::set('mail_body', 'A restored mail template for {name}');
        Settings::set('gravatar_enabled', '1');
        $person = Participant::forceCreate(['id' => 51, 'name' => 'Max', 'only_own_report' => true]);
        $sender = Participant::forceCreate(['id' => 52, 'name' => 'Erika']);
        $budget = Budget::factory()->create(['id' => 61, 'name' => 'Child']);
        Expense::forceCreate(['id' => 71, 'name' => 'Nursery', 'payer_id' => $person->id, 'budget_id' => $budget->id, 'amount_cents' => 23000, 'split_mode' => 'personal', 'shares' => [['participant_id' => $person->id]], 'start_month' => '2027-01', 'interval_months' => 1, 'notes' => 'Original cost notes']);
        Income::forceCreate(['id' => 81, 'name' => 'Benefit', 'participant_id' => $person->id, 'sender_id' => $sender->id, 'budget_id' => $budget->id, 'include_in_payment_plan' => true, 'amount_cents' => 25900, 'start_month' => '2027-01', 'interval_months' => 1]);
        app(CloseMonth::class)->close('2027-01', $user->id);

        return [$user, $person];
    }

    /** Save a backup and automatically remove the temporary archive after the test.
     * @return string Absolute path of the archive.
     */
    private function export(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'complete-backup-');
        $this->beforeApplicationDestroyed(function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });
        app(HouseholdBackup::class)->exportToZip($path);

        return $path;
    }

    /** Replace a dataset and update its checksum to exercise structural validation beyond integrity checks.
     * @param  list<array<string, mixed>>  $rows
     */
    private function rewrite(string $path, string $table, array $rows): void
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $manifest['checksums'][$table] = hash('sha256', $json);
        $zip->addFromString($table.'.json', $json);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();
    }

    /** Recover every durable table into an empty installation and retain working credentials and links. */
    public function test_complete_backup_restores_all_durable_data_to_an_empty_database(): void
    {
        [$user, $person] = $this->source();
        $expected = [];
        foreach (HouseholdBackup::TABLES as $table) {
            $expected[$table] = DB::table($table)->orderBy($table === 'settings' ? 'key' : 'id')->get()->map(fn (object $row): array => (array) $row)->all();
        }
        $expected['users'][0]['remember_token'] = null;
        $path = $this->export();
        foreach (array_reverse(HouseholdBackup::TABLES) as $table) {
            DB::table($table)->delete();
        }

        $this->assertTrue(app(HouseholdBackup::class)->importFromZip($path));

        foreach (HouseholdBackup::TABLES as $table) {
            $actual = DB::table($table)->orderBy($table === 'settings' ? 'key' : 'id')->get()->map(fn (object $row): array => (array) $row)->all();
            $this->assertSame($expected[$table], $actual, 'Restore mismatch: '.$table);
        }
        $restored = User::findOrFail($user->id);
        $this->assertTrue(Hash::check('RecoveryPassword123!', $restored->password));
        $this->assertTrue($restored->is_admin);
        $this->assertSame('de', $restored->locale);
        $this->assertSame(MonthlyDashboardLayout::defaults(), $restored->monthly_dashboard_layout);
        $this->assertSame($user->id, MonthlyClosing::sole()->closed_by);
        $this->assertTrue(Participant::findOrFail($person->id)->only_own_report);
        $this->assertSame(2900, MonthlyClosing::sole()->snapshot['budgets'][0]['closing_cents']);
        $this->assertSame('A restored mail template for {name}', Settings::mailBody());
        $this->assertGreaterThan($user->id, User::factory()->create(['is_admin' => true])->id);
        $this->assertGreaterThan(52, Participant::create(['name' => 'New person'])->id);
        $this->assertGreaterThan(61, Budget::factory()->create()->id);
    }

    /** Every application table must be backed up or explicitly classified as disposable runtime state. */
    public function test_backup_inventory_accounts_for_every_application_table(): void
    {
        $runtimeTables = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];
        $durableTables = array_values(array_diff(Schema::getTableListing(schemaQualified: false), $runtimeTables));
        sort($durableTables);
        $backedUpTables = HouseholdBackup::TABLES;
        sort($backedUpTables);

        $this->assertSame($durableTables, $backedUpTables, 'Classify new tables explicitly so backups cannot silently omit durable application data.');
    }

    /** Format metadata and checksums cover all persistent datasets without exporting ephemeral sessions. */
    public function test_archive_contains_verified_datasets_and_excludes_runtime_credentials(): void
    {
        $this->source();
        $path = $this->export();
        $zip = new ZipArchive;
        $zip->open($path);

        $manifest = json_decode($zip->getFromName('manifest.json'), true);

        $this->assertSame(3, $manifest['format']);
        $this->assertSame('splitnest', $manifest['app']);
        foreach (HouseholdBackup::TABLES as $table) {
            $this->assertSame(hash('sha256', $zip->getFromName($table.'.json')), $manifest['checksums'][$table]);
        }
        foreach (['sessions.json', 'password_reset_tokens.json', '.env', 'app.key', 'jobs.json'] as $file) {
            $this->assertFalse($zip->getFromName($file));
        }
        $zip->close();
    }

    /** Bit corruption or untracked edits are rejected before any current accounts or household rows change. */
    public function test_modified_dataset_fails_checksum_and_preserves_existing_data(): void
    {
        [$user, $person] = $this->source();
        $path = $this->export();
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('participants.json', '[]');
        $zip->close();

        try {
            app(HouseholdBackup::class)->importFromZip($path);
            $this->fail('Changed archive accepted');
        } catch (ValidationException $exception) {
            $this->assertSame(__('backup.errors.checksum', ['table' => 'participants']), $exception->getMessage());
        }
        $this->assertModelExists($user);
        $this->assertModelExists($person);
        $this->assertDatabaseCount('monthly_closings', 1);
    }

    /** Unknown, incomplete and malformed manifests do not silently downgrade to household-only restoration. */
    #[DataProvider('invalidManifests')]
    public function test_invalid_manifests_are_rejected_without_replacing_accounts(string $manifest): void
    {
        [$user] = $this->source();
        $path = $this->export();
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('manifest.json', $manifest);
        $zip->close();

        $this->expectException(ValidationException::class);
        try {
            app(HouseholdBackup::class)->importFromZip($path);
        } finally {
            $this->assertModelExists($user);
            $this->assertDatabaseCount('participants', 2);
        }
    }

    /** Manifests must identify this application and a supported, checksummed format.
     * @return array<string, array{string}>
     */
    public static function invalidManifests(): array
    {
        return [
            'malformed' => ['{'], 'wrong application' => ['{"app":"other","format":3}'],
            'future version' => ['{"app":"splitnest","format":999}'],
            'missing checksums' => ['{"app":"splitnest","format":3}'],
        ];
    }

    /** A structurally incomplete current backup cannot clear accounts or replace only part of the dataset. */
    public function test_missing_manifest_does_not_downgrade_a_complete_backup(): void
    {
        [$user] = $this->source();
        $path = $this->export();
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->deleteName('manifest.json');
        $zip->close();

        $this->expectException(ValidationException::class);
        try {
            app(HouseholdBackup::class)->importFromZip($path);
        } finally {
            $this->assertModelExists($user);
            $this->assertDatabaseCount('participants', 2);
        }
    }

    /** Passkey lookup keys are rebuilt for the target database from their original credential data. */
    public function test_passkey_lookup_encoding_is_portable_between_database_drivers(): void
    {
        $this->source();
        $path = $this->export();
        $passkey = Passkey::sole();
        $expected = $passkey->credential_id;
        $rows = DB::table('passkeys')->get()->map(fn (object $row): array => (array) $row)->all();
        $rows[0]['credential_id'] = 'different-source-database-encoding';
        $this->rewrite($path, 'passkeys', $rows);

        app(HouseholdBackup::class)->importFromZip($path);

        $this->assertSame($expected, Passkey::sole()->credential_id);
    }

    /** A structurally incomplete current backup cannot clear accounts or replace only part of the dataset. */
    public function test_complete_backup_requires_the_users_dataset(): void
    {
        [$user] = $this->source();
        $path = $this->export();
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->deleteName('users.json');
        $zip->close();

        try {
            app(HouseholdBackup::class)->importFromZip($path);
            $this->fail('Incomplete complete backup accepted');
        } catch (ValidationException $exception) {
            $this->assertSame(__('backup.errors.missing_table', ['table' => 'users']), $exception->getMessage());
        }
        $this->assertModelExists($user);
        $this->assertDatabaseCount('incomes', 1);
    }

    /** Unknown JSON references, duplicate keys and incompatible columns are caught before replacement. */
    #[DataProvider('invalidDatasets')]
    public function test_invalid_datasets_preserve_existing_data(string $table, string $change): void
    {
        [$user, $person] = $this->source();
        $path = $this->export();
        $rows = DB::table($table)->orderBy($table === 'settings' ? 'key' : 'id')->get()->map(fn (object $row): array => (array) $row)->all();
        match ($change) {
            'unknown payer' => $rows[0]['payer_id'] = 999999,
            'unknown share' => $rows[0]['shares'] = '[{"participant_id":999999}]',
            'unknown column' => $rows[0]['future_column'] = 'unsupported',
            'duplicate key' => $rows[] = $rows[0],
            'bad boolean' => $rows[0]['only_own_report'] = 'false',
            'bad snapshot' => $rows[0]['snapshot'] = '{',
            'no admin' => $rows[0]['is_admin'] = false,
            'bad passkey' => $rows[0]['data'] = '{}',
            'bad prices' => $rows[0]['price_changes'] = '[{"month":"2027-02","amount":"-1"}]',
            'bad archive month' => $rows[0]['archived_from_month'] = '2027-13',
            'archive with obligations' => $rows[0]['archived_from_month'] = '2027-02',
        };
        $this->rewrite($path, $table, $rows);

        try {
            app(HouseholdBackup::class)->importFromZip($path);
            $this->fail('Invalid dataset accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
        $this->assertModelExists($user);
        $this->assertModelExists($person);
        $this->assertDatabaseHas('expenses', ['name' => 'Nursery', 'payer_id' => $person->id]);
    }

    /** Each case represents a different persistent-data corruption or compatibility failure.
     * @return array<string, array{string, string}>
     */
    public static function invalidDatasets(): array
    {
        return [
            'broken foreign key' => ['expenses', 'unknown payer'], 'broken shares' => ['expenses', 'unknown share'],
            'unknown columns' => ['participants', 'unknown column'], 'duplicate IDs' => ['participants', 'duplicate key'],
            'invalid boolean' => ['participants', 'bad boolean'], 'invalid snapshot JSON' => ['monthly_closings', 'bad snapshot'],
            'no administrator' => ['users', 'no admin'], 'invalid passkey data' => ['passkeys', 'bad passkey'],
            'invalid price timeline' => ['expenses', 'bad prices'],
            'invalid archive boundary' => ['participants', 'bad archive month'],
            'archived recipient with future obligations' => ['participants', 'archive with obligations'],
        ];
    }

    /** A database constraint failure during insertion rolls back both deleted data and authentication cleanup. */
    public function test_insertion_failure_rolls_back_the_whole_restore(): void
    {
        [$user, $person] = $this->source();
        $path = $this->export();
        $rows = DB::table('users')->get()->map(fn (object $row): array => (array) $row)->all();
        unset($rows[0]['password']);
        $this->rewrite($path, 'users', $rows);
        DB::table('password_reset_tokens')->insert(['email' => 'old@example.test', 'token' => 'old-link', 'created_at' => now()]);

        try {
            app(HouseholdBackup::class)->importFromZip($path);
            $this->fail('Constraint failure accepted');
        } catch (ValidationException $exception) {
            $this->assertSame(__('backup.errors.restore_failed'), $exception->getMessage());
        }
        $this->assertTrue(Hash::check('RecoveryPassword123!', $user->fresh()->password));
        $this->assertModelExists($person);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'old@example.test', 'token' => 'old-link']);
    }

    /** Old household backups preserve target accounts/settings and remove dangling actor references. */
    public function test_legacy_backup_restores_on_a_new_instance_without_the_original_user_ids(): void
    {
        [$sourceUser] = $this->source();
        $path = $this->export();
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('manifest.json', '{"app":"splitnest","format":2}');
        $zip->close();
        foreach (array_reverse(HouseholdBackup::TABLES) as $table) {
            DB::table($table)->delete();
        }
        $targetAdmin = User::factory()->create(['id' => 1, 'is_admin' => true]);
        Settings::set('mail_body', 'Target template');

        $this->assertFalse(app(HouseholdBackup::class)->importFromZip($path));

        $this->assertModelExists($targetAdmin);
        $this->assertDatabaseMissing('users', ['id' => $sourceUser->id]);
        $this->assertNull(MonthlyClosing::sole()->closed_by);
        $this->assertSame('Target template', Settings::mailBody());
        $this->assertDatabaseCount('participants', 2);
    }

    /** Successful complete restoration clears active logins and stale password-reset links. */
    public function test_complete_restore_clears_sessions_and_password_reset_links(): void
    {
        [$user] = $this->source();
        $path = $this->export();
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => 'old', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'old-link', 'created_at' => now()]);

        app(HouseholdBackup::class)->importFromZip($path);

        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertNull($user->fresh()->remember_token);
    }

    /** Restoration through the admin page redirects to login after replacing account identities. */
    public function test_ui_restoration_signs_out_the_current_administrator(): void
    {
        [$user] = $this->source();
        $path = $this->export();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::disk('local')->put('backup-uploads/complete.zip', file_get_contents($path));

        Livewire::test(Backup::class)->mountAction('import')
            ->setActionData(['file' => ['00000000-0000-0000-0000-000000000000' => 'backup-uploads/complete.zip']])
            ->callMountedAction()->assertHasNoActionErrors()
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_admin' => true]);
        $this->assertFalse(Storage::disk('local')->exists('backup-uploads/complete.zip'));
    }
}
