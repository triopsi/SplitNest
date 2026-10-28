<?php

namespace App\Services;

use App\Models\MonthlyClosing;
use App\Models\Participant;

/** Projects a frozen closing onto an allowlist of one participant's costs and payments. */
final class ParticipantStatement
{
    /**
     * Expose only personal cost amounts, contributions and directly involving payments.
     * Household totals, earnings, budgets, allocation weights and other people's shares
     * never enter the template payload. Frozen names and amounts are preserved.
     *
     * @return array{month: string, saved_on: string, name: string, costs: list<array{name: string, kind: string, amount_cents: int}>, payments: list<array{from_name: string, to_name: string, outgoing: bool, amount_cents: int, reasons: list<array{name: string, amount_cents: int}>}>, outgoing_cents: int, incoming_cents: int}
     */
    public function forParticipant(MonthlyClosing $closing, Participant $participant): array
    {
        $report = $closing->snapshot ?? [];
        $person = collect($report['participants'] ?? [])->firstWhere('id', $participant->id);
        $costs = $payments = [];
        foreach ($report['expenses'] ?? [] as $expense) {
            $amount = $expense['allocations'][$participant->id] ?? 0;
            if ($amount > 0) {
                $costs[] = ['name' => $expense['name'], 'kind' => 'share', 'amount_cents' => $amount];
            }
        }
        foreach ($report['incomes'] ?? [] as $income) {
            if (($income['sender_id'] ?? null) === $participant->id && ($income['include_in_payment_plan'] ?? false) && $income['amount_cents'] > 0) {
                $costs[] = ['name' => $income['name'], 'kind' => 'contribution', 'amount_cents' => $income['amount_cents']];
            }
        }
        foreach ($report['payment_instructions'] ?? $report['transfers'] ?? [] as $payment) {
            if ($payment['from_id'] !== $participant->id && $payment['to_id'] !== $participant->id) {
                continue;
            }
            $reasons = [];
            if (($payment['settlement_cents'] ?? 0) > 0) {
                $reasons[] = ['name' => __('funding.settlement_part'), 'amount_cents' => $payment['settlement_cents']];
            }
            foreach ($payment['reasons'] ?? [] as $reason) {
                $reasons[] = ['name' => $reason['name'], 'amount_cents' => $reason['amount_cents']];
            }
            $payments[] = [
                'from_name' => $payment['from_name'], 'to_name' => $payment['to_name'],
                'outgoing' => $payment['from_id'] === $participant->id,
                'amount_cents' => $payment['amount_cents'], 'reasons' => $reasons,
            ];
        }

        return [
            'month' => $closing->month, 'saved_on' => $closing->closed_at?->format('d.m.Y H:i') ?? '',
            'name' => $person['name'] ?? $participant->name, 'costs' => $costs, 'payments' => $payments,
            'outgoing_cents' => array_sum(array_column(array_filter($payments, fn (array $payment): bool => $payment['outgoing']), 'amount_cents')),
            'incoming_cents' => array_sum(array_column(array_filter($payments, fn (array $payment): bool => ! $payment['outgoing']), 'amount_cents')),
        ];
    }
}
