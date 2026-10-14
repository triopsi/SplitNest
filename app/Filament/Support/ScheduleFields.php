<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * Shared form fields for the schedule of recurring entries (amount,
 * interval, first/last month), used by the expense and income forms.
 */
final class ScheduleFields
{
    /**
     * Build the schedule form fields.
     *
     * @return array<int, Field>
     */
    public static function make(): array
    {
        return [
            TextInput::make('amount')->label('Betrag in Euro')->suffix('€')->required()
                ->rules(['regex:/^\d{1,7}([.,]\d{1,2})?$/D'])->helperText('Zum Beispiel 1701,00. Keine Tausenderpunkte.'),
            Select::make('interval_months')->label('Wiederholung')->options([
                0 => 'Einmalig', 1 => 'Monatlich', 3 => 'Alle 3 Monate', 4 => 'Alle 4 Monate', 6 => 'Halbjährlich', 12 => 'Jährlich',
            ])->default(1)->required(),
            TextInput::make('start_month')->label('Erster Monat')->type('month')->default(now()->format('Y-m'))->required()->rules(['date_format:Y-m']),
            TextInput::make('end_month')->label('Letzter Monat (optional)')->type('month')->rules(['nullable', 'date_format:Y-m'])->afterOrEqual('start_month')
                ->helperText('Inklusive. Bei Änderungen alten Eintrag beenden und neuen Eintrag ab dem Folgemonat anlegen.'),
        ];
    }
}
