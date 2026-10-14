<?php

namespace App\Services;

use App\Events\MonthClosed;
use App\Models\MonthlyClosing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Finalizes a month: freezes the calculated settlement as an immutable
 * snapshot and records who closed it and when.
 */
final class CloseMonth
{
    /**
     * Create the service with its settlement calculator dependency.
     */
    public function __construct(private SettlementCalculator $calculator) {}

    /**
     * Close the given month and freeze its settlement snapshot.
     *
     * Unique month + row lock makes a repeated/concurrent request idempotent.
     *
     * @throws ValidationException When the month format is invalid.
     */
    public function close(string $month, int $userId): MonthlyClosing
    {
        Validator::make(['month' => $month], ['month' => ['required', 'date_format:Y-m']])->validate();

        return DB::transaction(function () use ($month, $userId) {
            DB::table('monthly_closings')->insertOrIgnore(['month' => $month, 'created_at' => now(), 'updated_at' => now()]);
            $closing = MonthlyClosing::where('month', $month)->lockForUpdate()->firstOrFail();
            if ($closing->closed_at) {
                return $closing;
            }
            $closing->update(['snapshot' => $this->calculator->calculate($month), 'closed_by' => $userId, 'closed_at' => now()]);
            MonthClosed::dispatch($closing);

            return $closing;
        }, 3);
    }
}
