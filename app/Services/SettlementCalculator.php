<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Participant;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Calculates the monthly settlement: income-weighted expense shares per
 * participant and the resulting balancing transfers between them.
 */
final class SettlementCalculator
{
    /**
     * Calculate the full settlement for one month.
     *
     * Sums up incomes and expenses per participant, splits every expense
     * according to its split mode and derives the balancing transfers.
     *
     * @return array{version: int, month: string, currency: string, participants: list<array{id: int, name: string, income_cents: int, paid_cents: int, share_cents: int, balance_cents: int, remaining_cents: int}>, incomes: list<array{name: string, participant_id: int, amount_cents: int}>, expenses: list<array{id: int, name: string, amount_cents: int, payer_id: int, split_mode: string, weights: array<int, int>, allocations: array<int, int>}>, total_expense_cents: int, total_income_cents: int, transfers: list<array{from_id: int, from_name: string, to_id: int, to_name: string, amount_cents: int}>}
     *
     * @throws ValidationException When the month is invalid, no participants exist or an expense cannot be allocated.
     */
    public function calculate(string $month): array
    {
        Validator::make(['month' => $month], ['month' => ['required', 'date_format:Y-m']])->validate();
        $people = Participant::orderBy('id')->get()->mapWithKeys(fn ($p) => [$p->id => [
            'id' => $p->id, 'name' => $p->name, 'income_cents' => 0, 'paid_cents' => 0,
            'share_cents' => 0, 'balance_cents' => 0, 'remaining_cents' => 0,
        ]])->all();
        if (! $people) {
            throw ValidationException::withMessages(['month' => 'Bitte zuerst Teilnehmer anlegen.']);
        }
        $incomeLines = [];
        foreach (Income::orderBy('id')->get() as $income) {
            if ($income->appliesTo($month)) {
                $people[$income->participant_id]['income_cents'] += $income->amount_cents;
                $incomeLines[] = ['name' => $income->name, 'participant_id' => $income->participant_id, 'amount_cents' => $income->amount_cents];
            }
        }
        $lines = [];
        foreach (Expense::orderBy('id')->get() as $expense) {
            if (! $expense->appliesTo($month)) {
                continue;
            }
            $expense->validateAllocation();
            $weights = [];
            foreach ($expense->shares as $share) {
                $id = (int) $share['participant_id'];
                $weights[$id] = match ($expense->split_mode) {
                    'income' => $people[$id]['income_cents'],
                    'percentage' => Money::cents($share['percentage'], 'shares'),
                    default => 1,
                };
            }
            if (array_sum($weights) === 0) {
                throw ValidationException::withMessages(['month' => "Für „{$expense->name}“ fehlt Einkommen bei allen zugeordneten Teilnehmern. Bitte Einkommen oder eine andere Aufteilung hinterlegen."]);
            }
            $allocations = $this->allocate($expense->amount_cents, $weights);
            foreach ($allocations as $id => $cents) {
                $people[$id]['share_cents'] += $cents;
            }
            $people[$expense->payer_id]['paid_cents'] += $expense->amount_cents;
            $lines[] = [
                'id' => $expense->id, 'name' => $expense->name, 'amount_cents' => $expense->amount_cents,
                'payer_id' => $expense->payer_id, 'split_mode' => $expense->split_mode,
                'weights' => $weights, 'allocations' => $allocations,
            ];
        }
        foreach ($people as &$person) {
            $person['balance_cents'] = $person['paid_cents'] - $person['share_cents'];
            $person['remaining_cents'] = $person['income_cents'] - $person['share_cents'];
        }
        unset($person);

        return [
            'version' => 1, 'month' => $month, 'currency' => 'EUR',
            'participants' => array_values($people), 'incomes' => $incomeLines, 'expenses' => $lines,
            'total_expense_cents' => array_sum(array_column($lines, 'amount_cents')),
            'total_income_cents' => array_sum(array_column($people, 'income_cents')),
            'transfers' => $this->transfers($people),
        ];
    }

    /**
     * Split an amount into exact cents, proportional to the given weights.
     *
     * Largest remainder allocation: exact cents, stable participant-ID tie break.
     *
     * @param  array<int, int>  $weights  Participant ID => non-negative weight.
     * @return array<int, int> Participant ID => allocated cents.
     *
     * @throws \InvalidArgumentException When the amount is negative or the weights are empty/negative.
     */
    public function allocate(int $amount, array $weights): array
    {
        ksort($weights, SORT_NUMERIC);
        $sum = array_sum($weights);
        if ($amount < 0 || $sum <= 0 || min($weights) < 0) {
            throw new \InvalidArgumentException('Ungültiger Betrag oder Gewichtung.');
        }
        $allocations = $remainders = [];
        foreach ($weights as $id => $weight) {
            [$whole, $remainder] = BigInteger::of($amount)->multipliedBy($weight)->quotientAndRemainder($sum);
            $allocations[$id] = $whole->toInt();
            $remainders[$id] = $remainder->toInt();
        }
        arsort($remainders, SORT_NUMERIC);
        $remaining = $amount - array_sum($allocations);
        foreach ($remainders as $id => $remainder) {
            if ($remaining-- <= 0) {
                break;
            }
            $allocations[$id]++;
        }

        return $allocations;
    }

    /**
     * Convert participant balances into a set of balancing transfers.
     *
     * Debtors pay creditors greedily; every transfer is as large as possible.
     *
     * @param  array<int, array{id: int, name: string, income_cents: int, paid_cents: int, share_cents: int, balance_cents: int, remaining_cents: int}>  $people
     * @return list<array{from_id: int, from_name: string, to_id: int, to_name: string, amount_cents: int}>
     */
    private function transfers(array $people): array
    {
        $creditors = $debtors = $transfers = [];
        foreach ($people as $id => $person) {
            if ($person['balance_cents'] > 0) {
                $creditors[$id] = $person['balance_cents'];
            } elseif ($person['balance_cents'] < 0) {
                $debtors[$id] = -$person['balance_cents'];
            }
        }
        foreach ($debtors as $from => $debt) {
            foreach ($creditors as $to => &$credit) {
                $amount = min($debt, $credit);
                if ($amount > 0) {
                    $transfers[] = ['from_id' => $from, 'from_name' => $people[$from]['name'], 'to_id' => $to, 'to_name' => $people[$to]['name'], 'amount_cents' => $amount];
                    $debt -= $amount;
                    $credit -= $amount;
                }
            }
            unset($credit);
        }

        return $transfers;
    }
}
