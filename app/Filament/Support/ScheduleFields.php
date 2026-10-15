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
            TextInput::make('amount')->label(__('schedule.amount'))->suffix('€')->required()
                ->rules(['regex:/^\d{1,7}([.,]\d{1,2})?$/D'])
                ->helperText(__('schedule.amount_hint')),
            Select::make('interval_months')->label(__('schedule.interval'))->options([
                0 => __('schedule.intervals.one_off'), 1 => __('schedule.intervals.monthly'), 3 => __('schedule.intervals.every_3'), 4 => __('schedule.intervals.every_4'), 6 => __('schedule.intervals.half_yearly'), 12 => __('schedule.intervals.yearly'),
            ])->default(1)->required()
                ->helperText(__('schedule.interval_hint')),
            TextInput::make('start_month')->label(__('schedule.start'))->type('month')->default(now()->format('Y-m'))->required()->rules(['date_format:Y-m'])
                ->helperText(__('schedule.start_hint')),
            TextInput::make('end_month')->label(__('schedule.end'))->type('month')->rules(['nullable', 'date_format:Y-m'])->afterOrEqual('start_month')
                ->helperText(__('schedule.end_hint')),
        ];
    }
}
