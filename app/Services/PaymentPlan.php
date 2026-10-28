<?php

namespace App\Services;

/** Combines household balancing with explicitly requested incoming or forwarded payments. */
final class PaymentPlan
{
    /**
     * Group obligations by payer and recipient while retaining their distinct reasons.
     * Income is already counted at its final recipient; these instructions never add income again.
     *
     * @param  list<array{from_id: int, from_name: string, to_id: int, to_name: string, amount_cents: int}>  $transfers
     * @param  list<array<string, mixed>>  $incomes
     * @param  array<int, array<string, mixed>>  $people
     * @return list<array{from_id: int|null, from_name: string, to_id: int, to_name: string, amount_cents: int, settlement_cents: int, income_cents: int, reasons: list<array{name: string, amount_cents: int}>}>
     */
    public function calculate(array $transfers, array $incomes, array $people): array
    {
        $payments = [];
        foreach ($transfers as $transfer) {
            $key = 'participant:'.$transfer['from_id'].':'.$transfer['to_id'];
            $payments[$key] = [...$transfer, 'settlement_cents' => $transfer['amount_cents'], 'income_cents' => 0, 'reasons' => []];
        }
        foreach ($incomes as $income) {
            if (! ($income['include_in_payment_plan'] ?? false) || $income['amount_cents'] === 0) {
                continue;
            }
            $senderId = $income['sender_id'];
            $recipientId = $income['participant_id'];
            $key = ($senderId ? 'participant:'.$senderId : 'external:'.$income['sender_name']).':'.$recipientId;
            $payments[$key] ??= [
                'from_id' => $senderId, 'from_name' => $income['sender_name'],
                'to_id' => $recipientId, 'to_name' => $people[$recipientId]['name'],
                'amount_cents' => 0, 'settlement_cents' => 0, 'income_cents' => 0, 'reasons' => [],
            ];
            $payments[$key]['amount_cents'] += $income['amount_cents'];
            $payments[$key]['income_cents'] += $income['amount_cents'];
            $payments[$key]['reasons'][] = ['name' => $income['name'], 'amount_cents' => $income['amount_cents']];
        }

        return array_values($payments);
    }
}
