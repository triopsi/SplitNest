<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Income;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Services\HouseholdBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_import_roundtrip_restores_all_data(): void
    {
        $a = Participant::create(['name' => 'Daniel']);
        $b = Participant::create(['name' => 'Svenja']);
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
}
