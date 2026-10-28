<?php

namespace App\Services;

use App\Models\MonthlyClosing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Reopens a closed month: discards the frozen snapshot so forgotten entries
 * can be added before the month is closed again. The closing row itself and
 * its month stay untouched.
 */
final class ReopenMonth
{
    /**
     * Reopen the given month by clearing its snapshot and closing metadata.
     *
     * The row lock makes concurrent reopen/close requests safe.
     *
     * @throws ValidationException When the month format is invalid or the month is not closed.
     */
    public function reopen(string $month): MonthlyClosing
    {
        Validator::make(['month' => $month], ['month' => ['required', 'date_format:Y-m']])->validate();

        return DB::transaction(function () use ($month) {
            $closing = MonthlyClosing::where('month', $month)->lockForUpdate()->first();
            if (! $closing?->closed_at) {
                throw ValidationException::withMessages(['month' => __('settlement.closing.not_closed')]);
            }
            $laterClosings = MonthlyClosing::where('month', '>', $month)->whereNotNull('closed_at')->get();
            if ($laterClosings->contains(fn (MonthlyClosing $later): bool => ! empty($later->snapshot['budgets']))) {
                throw ValidationException::withMessages(['month' => __('funding.later_closing')]);
            }
            $closing->update(['snapshot' => null, 'closed_by' => null, 'closed_at' => null]);

            return $closing;
        }, 3);
    }
}
