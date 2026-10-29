<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Participant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use JsonException;
use Spatie\LaravelPasskeys\Models\Passkey;
use Throwable;
use ZipArchive;

/** Back up all durable application data and restore validated archives without partial replacement. */
final class HouseholdBackup
{
    /** Durable tables in insertion order, with authentication parents before their references. */
    public const TABLES = ['users', 'participants', 'budgets', 'expenses', 'incomes', 'monthly_closings', 'settings', 'household_invitations', 'passkeys'];

    /** Datasets understood by the original household-only backup formats. */
    private const LEGACY_TABLES = ['participants', 'budgets', 'expenses', 'incomes', 'monthly_closings'];

    /**
     * Export a consistent data snapshot with checksums and format metadata.
     * Active sessions, reset links, queues and caches are deliberately not portable state.
     */
    public function exportToZip(string $targetPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw ValidationException::withMessages(['file' => __('backup.errors.export_failed')]);
        }
        try {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) && DB::transactionLevel() === 0) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $datasets = DB::transaction(function (): array {
                if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                }
                $datasets = [];
                foreach (self::TABLES as $table) {
                    $rows = DB::table($table)->orderBy($table === 'settings' ? 'key' : 'id')->get()->map(function (object $row) use ($table): array {
                        $attributes = (array) $row;
                        if ($table === 'users') {
                            $attributes['remember_token'] = null;
                        }

                        return $attributes;
                    })->all();
                    $datasets[$table] = json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                }

                return $datasets;
            });
            $checksums = [];
            foreach ($datasets as $table => $json) {
                if (! $zip->addFromString($table.'.json', $json)) {
                    throw ValidationException::withMessages(['file' => __('backup.errors.export_failed')]);
                }
                $checksums[$table] = hash('sha256', $json);
            }
            if (! $zip->addFromString('manifest.json', json_encode([
                'app' => 'splitnest', 'format' => 3, 'app_version' => config('app.version'),
                'database_driver' => DB::getDriverName(), 'exported_at' => now()->toIso8601String(),
                'checksums' => $checksums,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                throw ValidationException::withMessages(['file' => __('backup.errors.export_failed')]);
            }
        } catch (JsonException) {
            throw ValidationException::withMessages(['file' => __('backup.errors.export_failed')]);
        } finally {
            if (! $zip->close()) {
                throw ValidationException::withMessages(['file' => __('backup.errors.export_failed')]);
            }
        }
    }

    /**
     * Restore complete archives atomically; old household-only archives preserve existing accounts/settings.
     *
     * @return bool Whether accounts were restored and the caller must sign out.
     */
    public function importFromZip(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => __('backup.errors.not_zip')]);
        }
        try {
            $manifestJson = $zip->getFromName('manifest.json');
            if ($manifestJson === false && $zip->locateName('users.json') !== false) {
                throw ValidationException::withMessages(['file' => __('backup.errors.unsupported_format')]);
            }
            $manifest = $manifestJson === false ? ['app' => 'splitnest', 'format' => 1] : $this->decode($manifestJson);
            if (($manifest['app'] ?? null) !== 'splitnest' || ! in_array($manifest['format'] ?? null, [1, 2, 3], true)) {
                throw ValidationException::withMessages(['file' => __('backup.errors.unsupported_format')]);
            }
            $complete = $manifest['format'] === 3;
            $tables = $complete ? self::TABLES : self::LEGACY_TABLES;
            $data = [];
            $readOrder = ['participants', 'incomes', 'expenses', 'monthly_closings', 'budgets', 'users', 'settings', 'household_invitations', 'passkeys'];
            foreach (array_intersect($readOrder, $tables) as $table) {
                $json = $zip->getFromName($table.'.json');
                if ($table === 'budgets' && $json === false && $manifest['format'] === 1) {
                    $data[$table] = [];

                    continue;
                }
                if ($json === false) {
                    throw ValidationException::withMessages(['file' => __('backup.errors.missing_table', ['table' => $table])]);
                }
                if ($complete && (! is_string($manifest['checksums'][$table] ?? null) || ! hash_equals($manifest['checksums'][$table], hash('sha256', $json)))) {
                    throw ValidationException::withMessages(['file' => __('backup.errors.checksum', ['table' => $table])]);
                }
                $data[$table] = $this->decode($json, $table);
                $this->validateRows($table, $data[$table]);
            }
        } finally {
            $zip->close();
        }
        $this->validateReferences($data, $complete);
        if ($complete) {
            foreach ($data['users'] as &$user) {
                $user['remember_token'] = null;
            }
            unset($user);
            foreach ($data['passkeys'] as &$row) {
                try {
                    $passkey = (new Passkey)->setRawAttributes($row);
                    $row['credential_id'] = Passkey::encodeCredentialId($passkey->data->publicKeyCredentialId);
                } catch (Throwable) {
                    throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => 'passkeys'])]);
                }
            }
            unset($row);
        } else {
            $userIds = DB::table('users')->pluck('id')->all();
            foreach ($data['monthly_closings'] as &$closing) {
                if (! in_array($closing['closed_by'] ?? null, $userIds, true)) {
                    $closing['closed_by'] = null;
                }
            }
            unset($closing);
        }
        try {
            DB::transaction(function () use ($data, $tables, $complete): void {
                $previousMaxIds = [];
                foreach (array_diff($tables, ['settings']) as $table) {
                    $previousMaxIds[$table] = (int) DB::table($table)->max('id');
                }
                if ($complete) {
                    DB::table('sessions')->delete();
                    DB::table('password_reset_tokens')->delete();
                }
                foreach (array_reverse($tables) as $table) {
                    DB::table($table)->delete();
                }
                foreach ($tables as $table) {
                    foreach (array_chunk($data[$table], 100) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
                try {
                    foreach (Participant::whereNotNull('archived_from_month')->get() as $participant) {
                        app(ArchiveParticipant::class)->validate($participant, $participant->archived_from_month);
                    }
                } catch (ValidationException) {
                    throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => 'participants'])]);
                }
                $this->resetIdSequences($tables, $previousMaxIds);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['file' => __('backup.errors.restore_failed')]);
        }

        return $complete;
    }

    /** Decode JSON without accepting objects where a list of table rows is expected.
     * @return array<array-key, mixed>
     */
    private function decode(string $json, ?string $table = null): array
    {
        try {
            $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $value = null;
        }
        if (! is_array($value) || ($table !== null && (! array_is_list($value) || ! str_starts_with(ltrim($json), '[')))) {
            throw ValidationException::withMessages(['file' => $table
                ? __('backup.errors.invalid_rows', ['table' => $table]) : __('backup.errors.unsupported_format')]);
        }

        return $value;
    }

    /** Reject malformed keys, duplicates and schema-incompatible columns before deleting any live data.
     * @param  list<array<string, mixed>>  $rows
     */
    private function validateRows(string $table, array $rows): void
    {
        $key = $table === 'settings' ? 'key' : 'id';
        $columns = Schema::getColumnListing($table);
        $seen = [];
        $jsonFields = match ($table) {
            'expenses' => ['shares', 'price_changes'],
            'monthly_closings' => ['snapshot'],
            'users' => ['monthly_dashboard_layout'],
            'passkeys' => ['data'],
            default => [],
        };
        $booleanFields = match ($table) {
            'users' => ['is_admin'],
            'participants' => ['only_own_report'],
            'incomes' => ['affects_allocation', 'include_in_payment_plan'],
            default => [],
        };
        foreach ($rows as $row) {
            if ($table === 'participants' && ($row['archived_from_month'] ?? null) !== null && (! is_string($row['archived_from_month']) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $row['archived_from_month']) !== 1)) {
                throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => $table])]);
            }
            $id = is_array($row) ? ($row[$key] ?? null) : null;
            if (! is_array($row) || ($key === 'id' ? (! is_int($id) || $id < 1) : (! is_string($id) || $id === '')) || isset($seen[$id]) || array_diff(array_keys($row), $columns)) {
                throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => $table])]);
            }
            foreach ($row as $value) {
                if (! is_null($value) && ! is_scalar($value)) {
                    throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => $table])]);
                }
            }
            foreach ($booleanFields as $field) {
                if (array_key_exists($field, $row) && ! in_array($row[$field], [0, 1, false, true], true)) {
                    throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => $table])]);
                }
            }
            foreach ($jsonFields as $field) {
                if (($row[$field] ?? null) !== null) {
                    try {
                        $this->decode((string) $row[$field]);
                    } catch (ValidationException) {
                        throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => $table])]);
                    }
                }
            }
            $seen[$id] = true;
            if ($table === 'expenses') {
                try {
                    (new Expense)->setRawAttributes($row)->validatePriceChanges();
                } catch (ValidationException) {
                    throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => $table])]);
                }
            }
        }
    }

    /** Validate foreign keys and JSON participant references against the incoming dataset.
     * @param  array<string, list<array<string, mixed>>>  $data
     */
    private function validateReferences(array $data, bool $complete): void
    {
        $ids = [];
        foreach ($data as $table => $rows) {
            $ids[$table] = array_column($rows, 'id');
        }
        $relations = [
            'expenses' => ['payer_id' => 'participants', 'budget_id' => 'budgets'],
            'incomes' => ['participant_id' => 'participants', 'sender_id' => 'participants', 'budget_id' => 'budgets', 'expense_id' => 'expenses'],
        ];
        if ($complete) {
            $relations += ['monthly_closings' => ['closed_by' => 'users'], 'household_invitations' => ['invited_by' => 'users'], 'passkeys' => ['authenticatable_id' => 'users']];
            if ($data['users'] && ! collect($data['users'])->contains(fn (array $user): bool => (bool) ($user['is_admin'] ?? false))) {
                throw ValidationException::withMessages(['file' => __('backup.errors.no_admin')]);
            }
        }
        foreach ($relations as $table => $fields) {
            foreach ($data[$table] as $row) {
                foreach ($fields as $field => $parent) {
                    if (($row[$field] ?? null) !== null && ! in_array($row[$field], $ids[$parent], true)) {
                        throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => $table])]);
                    }
                }
            }
        }
        foreach ($data['expenses'] as $row) {
            $shares = $this->decode($row['shares'] ?? '', 'expenses');
            foreach ($shares as $share) {
                if (! is_array($share) || ! in_array((int) ($share['participant_id'] ?? 0), $ids['participants'], true)) {
                    throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => 'expenses'])]);
                }
            }
        }
    }

    /** Reset numeric sequences after restoration; settings use string keys instead of numeric IDs.
     * @param  list<string>  $tables
     * @param  array<string, int>  $previousMaxIds
     */
    private function resetIdSequences(array $tables, array $previousMaxIds): void
    {
        foreach (array_diff($tables, ['settings']) as $table) {
            $nextId = max($previousMaxIds[$table], (int) DB::table($table)->max('id')) + 1;
            if (DB::getDriverName() === 'pgsql') {
                DB::select("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), ?, false)", [$nextId]);
            } elseif (DB::getDriverName() === 'sqlite' && Schema::hasTable('sqlite_sequence')) {
                DB::table('sqlite_sequence')->where('name', $table)->update(['seq' => $nextId - 1]);
            }
        }
    }
}
