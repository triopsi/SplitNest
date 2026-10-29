<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Retire participant identities only after scheduled obligations and reserved cash have been resolved. */
final class ArchiveParticipant
{
    /** Save a month boundary while retaining the participant and all historical references. */
    public function archive(Participant $participant, string $month): void
    {
        DB::transaction(function () use ($participant, $month): void {
            Participant::whereKey($participant->id)->lockForUpdate()->firstOrFail()
                ->forceFill(['archived_from_month' => $month])->save();
        });
    }

    /** Validate the archive boundary without rewriting any expenses, receipts or closed reports. */
    public function validate(Participant $participant, string $month): void
    {
        Validator::make(['archived_from_month' => $month], ['archived_from_month' => ['required', 'date_format:Y-m']])->validate();
        $fail = function (string $key) use ($participant): never {
            throw ValidationException::withMessages(['archived_from_month' => __('participants.archive.'.$key, ['name' => $participant->name])]);
        };
        foreach (Income::where('participant_id', $participant->id)->orWhere('sender_id', $participant->id)->get() as $income) {
            if ($income->nextBillingMonth($month) !== null) {
                $fail('has_incomes');
            }
        }
        foreach (Expense::cursor() as $expense) {
            $involved = $expense->payer_id === $participant->id || collect($expense->shares)->contains(fn (array $share): bool => (int) $share['participant_id'] === $participant->id);
            if ($involved && $expense->nextBillingMonth($month) !== null) {
                $fail('has_expenses');
            }
        }
        foreach (MonthlyClosing::where('month', '>=', $month)->whereNotNull('closed_at')->cursor() as $closing) {
            if (collect($closing->snapshot['participants'] ?? [])->contains(fn (array $person): bool => (int) $person['id'] === $participant->id)) {
                $fail('has_closings');
            }
        }
        $previousMonth = Carbon::createFromFormat('!Y-m', $month)->subMonth()->format('Y-m');
        $funding = app(BudgetFunding::class)->calculate($previousMonth, Income::orderBy('id')->get(), Expense::orderBy('id')->get());
        foreach ($funding['budgets'] as $budget) {
            if (($budget['balances'][$participant->id] ?? 0) > 0) {
                $fail('has_reserves');
            }
        }
    }
}
