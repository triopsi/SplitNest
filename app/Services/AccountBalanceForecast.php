<?php

namespace App\Services;

use App\Models\MonthlyClosing;
use App\Models\Participant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Project observed bank balances independently of monthly income and expense allocation. */
final class AccountBalanceForecast
{
    /**
     * Replay monthly cash changes after each participant's observed month-end balance.
     * Savings held by that person stay on their account; available cash excludes reserves.
     * Frozen settlements supply historical changes. Missing months block later projections
     * instead of silently assuming zero. Changing the starting point never rewrites closings.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function between(string $fromMonth, string $toMonth): array
    {
        Validator::make(['from' => $fromMonth, 'to' => $toMonth], [
            'from' => ['required', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:2100-12'],
            'to' => ['required', 'date_format:Y-m', 'after_or_equal:from', 'before_or_equal:2100-12'],
        ])->validate();
        $participants = Participant::whereNotNull('account_balance_month')->where('account_balance_month', '<=', $toMonth)->get();
        if ($participants->isEmpty()) {
            return [];
        }
        $firstMonth = $participants->min('account_balance_month');
        $closings = MonthlyClosing::whereBetween('month', [$firstMonth, $toMonth])->whereNotNull('closed_at')->get()->keyBy('month');
        $months = [];
        for ($cursor = Carbon::createFromFormat('!Y-m', $firstMonth); $cursor->format('Y-m') <= $toMonth; $cursor->addMonth()) {
            $month = $cursor->format('Y-m');
            if (! $closings->has($month)) {
                $months[] = $month;
            }
        }
        $reports = app(SettlementCalculator::class)->calculateMany($months);
        $balances = $recovered = $errors = $result = [];
        for ($cursor = Carbon::createFromFormat('!Y-m', $firstMonth); $cursor->format('Y-m') <= $toMonth; $cursor->addMonth()) {
            $month = $cursor->format('Y-m');
            $active = $participants->filter(fn (Participant $person): bool => $person->account_balance_month <= $month && ($person->archived_from_month === null || $person->archived_from_month > $month));
            if ($active->isEmpty()) {
                continue;
            }
            try {
                $report = $closings->get($month)?->snapshot ?? $reports[$month];
                if ($report instanceof ValidationException) {
                    throw $report;
                }
                $people = collect($report['participants'])->keyBy('id');
                $problem = null;
            } catch (ValidationException $exception) {
                $people = collect();
                $problem = collect($exception->errors())->flatten()->implode(' ');
            }
            foreach ($active as $participant) {
                $id = $participant->id;
                $person = $people->get($id);
                $openingBalance = $balances[$id] ?? $participant->account_balance_cents;
                $isStartingMonth = $month === $participant->account_balance_month;
                if ($isStartingMonth) {
                    $balances[$id] = $participant->account_balance_cents;
                } elseif ($problem !== null || $person === null) {
                    $errors[$id] ??= $problem ?? __('participants.account.missing_month');
                } elseif (! isset($errors[$id])) {
                    $balances[$id] += $person['income_cents'] - $person['share_cents'] + ($person['savings_deposited_cents'] ?? 0);
                }
                if (! isset($errors[$id]) && $participant->account_balance_cents < 0 && $balances[$id] >= 0) {
                    $recovered[$id] ??= $month;
                }
                if ($month >= $fromMonth) {
                    $result[$month][$id] = [
                        'observed_balance_cents' => $participant->account_balance_cents,
                        'observed_month' => $participant->account_balance_month,
                        'opening_balance_cents' => $openingBalance,
                        'is_starting_month' => $isStartingMonth,
                        'income_cents' => $isStartingMonth ? 0 : ($person['income_cents'] ?? 0),
                        'share_cents' => $isStartingMonth ? 0 : ($person['share_cents'] ?? 0),
                        'savings_deposited_cents' => $isStartingMonth ? 0 : ($person['savings_deposited_cents'] ?? 0),
                        'reserved_cents' => $person['reserved_cents'] ?? 0,
                        'balance_cents' => isset($errors[$id]) ? null : $balances[$id],
                        'available_cents' => isset($errors[$id]) || $person === null ? null : $balances[$id] - ($person['reserved_cents'] ?? 0),
                        'recovered_month' => $recovered[$id] ?? null,
                        'error' => $errors[$id] ?? $problem,
                    ];
                }
            }
        }

        return $result;
    }
}
