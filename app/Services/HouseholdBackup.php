<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Exports and imports the whole household dataset (participants, incomes,
 * expenses and monthly closings) as a ZIP file of JSON documents.
 *
 * Used for backups and for moving data between instances. User accounts are
 * deliberately excluded — logins are recreated via the splitnest:user command.
 */
final class HouseholdBackup
{
    /** Tables included in a backup, in insert-safe order (parents first). */
    public const TABLES = ['participants', 'incomes', 'expenses', 'monthly_closings'];

    /**
     * Write all household tables as JSON files into a ZIP archive.
     *
     * @param  string  $targetPath  Filesystem path the ZIP is written to.
     */
    public function exportToZip(string $targetPath): void
    {
        $zip = new ZipArchive;
        $zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', (string) json_encode([
            'app' => 'splitnest', 'format' => 1, 'exported_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
        foreach (self::TABLES as $table) {
            $rows = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            $zip->addFromString($table.'.json', (string) json_encode($rows, JSON_PRETTY_PRINT));
        }
        $zip->close();
    }

    /**
     * Replace all household data with the contents of a backup ZIP.
     *
     * Existing rows are removed first; IDs from the backup are kept so
     * references (payer, shares, closings) stay intact. Runs in one transaction.
     *
     * @throws ValidationException When the file is not a valid SplitNest backup.
     */
    public function importFromZip(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => __('backup.errors.not_zip')]);
        }
        $data = [];
        foreach (self::TABLES as $table) {
            $json = $zip->getFromName($table.'.json');
            $rows = $json === false ? null : json_decode($json, true);
            if (! is_array($rows)) {
                $zip->close();
                throw ValidationException::withMessages(['file' => __('backup.errors.missing_table', ['table' => $table])]);
            }
            foreach ($rows as $row) {
                if (! is_array($row) || ! array_key_exists('id', $row)) {
                    $zip->close();
                    throw ValidationException::withMessages(['file' => __('backup.errors.invalid_rows', ['table' => $table])]);
                }
            }
            $data[$table] = $rows;
        }
        $zip->close();

        DB::transaction(function () use ($data): void {
            foreach (array_reverse(self::TABLES) as $table) {
                DB::table($table)->delete();
            }
            foreach (self::TABLES as $table) {
                foreach (array_chunk($data[$table], 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
            $this->resetIdSequences();
        });
    }

    /**
     * Re-align the auto-increment sequences with the imported max IDs,
     * so new records do not collide with restored ones.
     */
    private function resetIdSequences(): void
    {
        foreach (self::TABLES as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)");
            } elseif (DB::getDriverName() === 'sqlite' && DB::getSchemaBuilder()->hasTable('sqlite_sequence')) {
                DB::table('sqlite_sequence')->where('name', $table)->update(['seq' => DB::table($table)->max('id') ?? 0]);
            } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.((int) (DB::table($table)->max('id') ?? 0) + 1));
            }
        }
    }
}
