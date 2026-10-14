<?php

namespace App\Models\Concerns;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Shared schedule behaviour for recurring entries ({@see Expense}, {@see Income}):
 * decimal-amount accessor backed by integer cents, schedule validation on save,
 * and the check whether an entry applies to a given month.
 */
trait HasSchedule
{
    /**
     * Validate the schedule fields (amount, months, interval) on every save.
     */
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

    /**
     * Accessor/mutator pair mapping the decimal "amount" to integer "amount_cents".
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn () => Money::decimal((int) $this->amount_cents),
            set: fn ($value) => ['amount_cents' => Money::cents($value)],
        );
    }

    /**
     * Determine whether this entry applies to the given month (Y-m).
     *
     * Checks the start/end boundaries first, then the interval rhythm
     * (interval 0 means the entry applies to its start month only).
     */
    public function appliesTo(string $month): bool
    {
        if ($month < $this->start_month || ($this->end_month && $month > $this->end_month)) {
            return false;
        }
        $distance = ((int) substr($month, 0, 4) - (int) substr($this->start_month, 0, 4)) * 12
            + (int) substr($month, 5, 2) - (int) substr($this->start_month, 5, 2);

        return $this->interval_months === 0 ? $distance === 0 : $distance % $this->interval_months === 0;
    }

    /**
     * The next month (Y-m) in which this entry is billed, viewed from $from.
     *
     * Returns null when the entry no longer occurs: a one-off in the past or
     * the next occurrence lying beyond the end month.
     */
    public function nextBillingMonth(string $from): ?string
    {
        $start = $this->start_month;
        if ($this->interval_months === 0) {
            return $start >= $from ? $start : null;
        }
        $distance = ((int) substr($from, 0, 4) - (int) substr($start, 0, 4)) * 12
            + (int) substr($from, 5, 2) - (int) substr($start, 5, 2);
        if ($distance <= 0) {
            $next = $start;
        } else {
            $offset = ($this->interval_months - ($distance % $this->interval_months)) % $this->interval_months;
            $next = Carbon::createFromFormat('Y-m', $from)->addMonths($offset)->format('Y-m');
        }

        return $this->end_month && $next > $this->end_month ? null : $next;
    }
}
