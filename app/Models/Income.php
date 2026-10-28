<?php

namespace App\Models;

use App\Models\Concerns\HasSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * A participant's recurring or one-off receipt, optionally used to weigh cost splits.
 *
 * Amounts are stored as integer cents; see {@see Expense} for the shared convention.
 *
 * @property int $participant_id
 * @property string $name
 * @property string $amount Decimal amount (mapped to cents on write).
 * @property int $amount_cents
 * @property string $start_month First applicable month (Y-m).
 * @property string|null $end_month Last applicable month (Y-m), null = open-ended.
 * @property int $interval_months 0 = one-off, otherwise every n months.
 * @property bool $affects_allocation Whether this receipt contributes to income-based weights.
 * @property int|null $budget_id Earmarked pool receiving this money.
 * @property int|null $expense_id Bill reduced by this reimbursement.
 * @property int|null $sender_id Household member forwarding this receipt to its recipient.
 * @property string|null $sender_name External payer, for example a friend sharing a subscription.
 * @property bool $include_in_payment_plan Show the scheduled payment in addition to the cost settlement.
 */
class Income extends Model
{
    use HasSchedule;

    protected $fillable = ['participant_id', 'name', 'amount', 'amount_cents', 'start_month', 'end_month', 'interval_months', 'affects_allocation', 'budget_id', 'expense_id', 'sender_id', 'sender_name', 'include_in_payment_plan'];

    protected $attributes = ['affects_allocation' => true, 'include_in_payment_plan' => false];

    protected $appends = ['amount'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'interval_months' => 'integer', 'affects_allocation' => 'boolean', 'include_in_payment_plan' => 'boolean'];
    }

    /** Validate funding destinations and exclude earmarked receipts from salary weighting. */
    protected static function booted(): void
    {
        static::saving(function (self $income): void {
            $income->sender_name = trim($income->sender_name ?? '') ?: null;
            Validator::make($income->getAttributes(), [
                'participant_id' => ['required', 'exists:participants,id'],
                'affects_allocation' => ['required', 'boolean'],
                'budget_id' => ['nullable', 'exists:budgets,id', 'prohibits:expense_id'],
                'expense_id' => ['nullable', 'exists:expenses,id'],
                'sender_id' => [Rule::requiredIf($income->include_in_payment_plan && ! $income->sender_name), 'nullable', 'exists:participants,id', 'different:participant_id', 'prohibits:sender_name'],
                'sender_name' => ['nullable', 'string', 'max:255'],
                'include_in_payment_plan' => ['required', 'boolean'],
            ])->validate();
            if ($income->budget_id || $income->expense_id || $income->sender_id) {
                $income->affects_allocation = false;
            }
        });
    }

    /** The household member forwarding the receipt, distinct from the final recipient. */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'sender_id');
    }

    /** The earmarked pool receiving this income. */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /** The bill reduced by this reimbursement. */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * The participant this income belongs to.
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
