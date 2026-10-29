<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MonthlyClosing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Replays earmarked cash by month, preserving the account holder and frozen carryovers. */
final class BudgetFunding
{
    /**
     * Apply reimbursements first, then spend budgets in stable expense-ID order.
     * Frozen closing balances anchor the next month; open months are projections.
     *
     * @param  Collection<int, Income>  $incomes
     * @param  Collection<int, Expense>  $expenses
     * @return array{budgets: list<array{id: int, name: string, opening_cents: int, income_cents: int, spent_cents: int, closing_cents: int, opening_balances: array<int, int>, balances: array<int, int>}>, expenses: array<int, array{reimbursed_cents: int, budget_cents: int, allocations: array<int, int>}>}
     */
    public function calculate(string $month, Collection $incomes, Collection $expenses): array
    {
        $budgets = Budget::orderBy('id')->get();
        $balances = $budgets->mapWithKeys(fn (Budget $budget): array => [$budget->id => []])->all();
        $firstMonth = $incomes->whereNotNull('budget_id')->min('start_month') ?? $month;
        $firstMonth = min($month, $firstMonth);
        $closings = MonthlyClosing::where('month', '<', $month)->whereNotNull('closed_at')->orderByDesc('month')->get();
        foreach ($closings as $closing) {
            if (! array_key_exists('budgets', $closing->snapshot)) {
                continue;
            }
            foreach ($closing->snapshot['budgets'] as $budget) {
                $balances[$budget['id']] = $budget['balances'];
            }
            $firstMonth = Carbon::createFromFormat('!Y-m', $closing->month)->addMonth()->format('Y-m');
            break;
        }
        $calculator = new SettlementCalculator;
        $result = ['budgets' => [], 'expenses' => []];
        for ($cursor = Carbon::createFromFormat('!Y-m', $firstMonth); $cursor->format('Y-m') <= $month; $cursor->addMonth()) {
            $currentMonth = $cursor->format('Y-m');
            $activeIncomes = $incomes->filter(fn (Income $income): bool => $income->appliesTo($currentMonth));
            $activeExpenses = $expenses->filter(fn (Expense $expense): bool => $expense->appliesTo($currentMonth))->keyBy('id');
            $openingBalances = $balances;
            $receipts = $spent = $funding = [];
            foreach ($activeIncomes as $income) {
                if ($income->budget_id) {
                    $balances[$income->budget_id][$income->participant_id] = ($balances[$income->budget_id][$income->participant_id] ?? 0) + $income->amount_cents;
                    $receipts[$income->budget_id] = ($receipts[$income->budget_id] ?? 0) + $income->amount_cents;
                }
                if ($income->expense_id) {
                    if (! isset($activeExpenses[$income->expense_id])) {
                        if ($currentMonth === $month) {
                            throw ValidationException::withMessages(['month' => __('funding.refund_not_due', ['name' => $income->name])]);
                        }

                        continue;
                    }
                    $line = $funding[$income->expense_id] ?? ['reimbursed_cents' => 0, 'budget_cents' => 0, 'allocations' => []];
                    $line['reimbursed_cents'] += $income->amount_cents;
                    $line['allocations'][$income->participant_id] = ($line['allocations'][$income->participant_id] ?? 0) + $income->amount_cents;
                    if ($line['reimbursed_cents'] > $activeExpenses[$income->expense_id]->amountForMonth($currentMonth)) {
                        throw ValidationException::withMessages(['month' => __('funding.refund_exceeds_cost', ['name' => $activeExpenses[$income->expense_id]->name])]);
                    }
                    $funding[$income->expense_id] = $line;
                }
            }
            foreach ($activeExpenses as $expense) {
                $line = $funding[$expense->id] ?? ['reimbursed_cents' => 0, 'budget_cents' => 0, 'allocations' => []];
                if ($expense->budget_id) {
                    $available = array_sum($balances[$expense->budget_id] ?? []);
                    $amount = min($expense->amountForMonth($currentMonth) - $line['reimbursed_cents'], $available);
                    if ($amount > 0) {
                        $withdrawals = $calculator->allocate($amount, $balances[$expense->budget_id]);
                        foreach ($withdrawals as $participantId => $cents) {
                            $balances[$expense->budget_id][$participantId] -= $cents;
                            $line['allocations'][$participantId] = ($line['allocations'][$participantId] ?? 0) + $cents;
                        }
                        $line['budget_cents'] = $amount;
                        $spent[$expense->budget_id] = ($spent[$expense->budget_id] ?? 0) + $amount;
                    }
                }
                $funding[$expense->id] = $line;
            }
            $result = [
                'budgets' => $budgets->map(fn (Budget $budget): array => [
                    'id' => $budget->id, 'name' => $budget->name,
                    'opening_cents' => array_sum($openingBalances[$budget->id] ?? []),
                    'income_cents' => $receipts[$budget->id] ?? 0,
                    'spent_cents' => $spent[$budget->id] ?? 0,
                    'closing_cents' => array_sum($balances[$budget->id] ?? []),
                    'opening_balances' => $openingBalances[$budget->id] ?? [],
                    'balances' => $balances[$budget->id] ?? [],
                ])->all(),
                'expenses' => $funding,
            ];
        }

        return $result;
    }
}
