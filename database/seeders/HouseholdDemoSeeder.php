<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HouseholdDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Participant::exists() || Income::exists() || Expense::exists()) {
            throw new \RuntimeException('Beispieldaten werden nur in einen leeren Haushalt eingespielt.');
        }
        DB::transaction(function () {
            $daniel = Participant::create(['name' => 'Daniel']);
            $svenja = Participant::create(['name' => 'Svenja']);
            foreach ([[$daniel, 340000], [$svenja, 280000]] as [$p,$amount]) {
                Income::create(['participant_id' => $p->id, 'name' => 'Nettogehalt', 'amount_cents' => $amount, 'start_month' => '2027-01', 'interval_months' => 1]);
            }
            foreach (['Miete' => 170100, 'Strom' => 21800, 'Gas' => 10200, 'Wasser' => 5000, 'Spotify' => 1700, 'Disney+' => 1599, 'Netflix' => 1399] as $name => $amount) {
                Expense::create(['name' => $name, 'amount_cents' => $amount, 'payer_id' => $daniel->id, 'split_mode' => 'income',
                    'shares' => [['participant_id' => $daniel->id], ['participant_id' => $svenja->id]], 'start_month' => '2027-01', 'interval_months' => 1]);
            }
            $personal = [
                [$daniel, ['Auto' => 13000, 'Grover MacBook' => 11900, 'Server' => 11500, 'Fonds / Sparen' => 10000, 'Kita' => 9100, 'ADAC' => 3950, 'Grover Staubsaugerroboter' => 2790, 'ChatGPT' => 2300, 'Handy Congstar' => 2000, 'Terminus SSH' => 1200, 'MS Office' => 700, 'Discovery+' => 599, 'GitHub' => 500, 'iCloud' => 300, 'Google Foto' => 200]],
                [$svenja, ['Auto' => 13000, 'Kita Phillip' => 4650]],
            ];
            foreach ($personal as [$p,$items]) {
                foreach ($items as $name => $amount) {
                    Expense::create(['name' => $name, 'amount_cents' => $amount, 'payer_id' => $p->id, 'split_mode' => 'personal', 'shares' => [['participant_id' => $p->id]], 'start_month' => '2027-01', 'interval_months' => 1]);
                }
            }
            $this->command?->info('Daniel, Svenja und bekannte Beträge für 2027 angelegt. Haushaltsgeld und unbekannte Beträge bitte ergänzen. Kein Login angelegt.');
        });
    }
}
