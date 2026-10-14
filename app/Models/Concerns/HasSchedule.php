<?php

namespace App\Models\Concerns;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Validator;

trait HasSchedule
{
    public static function bootHasSchedule(): void
    {
        static::saving(function ($record): void {
            Validator::make($record->getAttributes(), [
                'name' => ['required', 'string', 'max:255'],
                'amount_cents' => ['required', 'integer', 'min:0', 'max:999999999'],
                'start_month' => ['required', 'date_format:Y-m'],
                'end_month' => ['nullable', 'date_format:Y-m', 'after_or_equal:start_month'],
                'interval_months' => ['required', 'integer', 'in:0,1,3,4,6,12'],
            ])->validate();
        });
    }

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn () => Money::decimal((int) $this->amount_cents),
            set: fn ($value) => ['amount_cents' => Money::cents($value)],
        );
    }

    public function appliesTo(string $month): bool
    {
        if ($month < $this->start_month || ($this->end_month && $month > $this->end_month)) {
            return false;
        }
        $distance = ((int) substr($month, 0, 4) - (int) substr($this->start_month, 0, 4)) * 12
            + (int) substr($month, 5, 2) - (int) substr($this->start_month, 5, 2);

        return $this->interval_months === 0 ? $distance === 0 : $distance % $this->interval_months === 0;
    }
}
