<?php

namespace App\Models;

use App\Models\Concerns\HasSchedule;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A recurring or one-off household expense with its allocation across participants.
 *
 * Amounts are stored as integer cents ({@see Expense::$amount_cents}); the decimal
 * representation is exposed through the {@see Expense::$amount} accessor.
 *
 * @property int $payer_id ID of the participant who pays the bill.
 * @property string $name
 * @property string $amount Decimal amount (mapped to cents on write).
 * @property int $amount_cents
 * @property string $split_mode One of the {@see Expense::MODES} keys.
 * @property array<int, array{participant_id: int, percentage?: string}> $shares
 * @property string $start_month First applicable month (Y-m).
 * @property string|null $end_month Last applicable month (Y-m), null = open-ended.
 * @property int $interval_months 0 = one-off, otherwise every n months.
 */
class Expense extends Model
{
    use HasSchedule;

    /** Available cost-splitting strategies (key => German UI label). */
    public const MODES = ['percentage' => 'Feste Prozente', 'income' => 'Nach Einkommen', 'equal' => 'Gleiche Anteile', 'personal' => 'Persönlich (100 %)'];

    protected $fillable = ['payer_id', 'name', 'amount', 'amount_cents', 'split_mode', 'shares', 'start_month', 'end_month', 'interval_months', 'notes'];

    protected $appends = ['amount'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'interval_months' => 'integer', 'shares' => 'array'];
    }

    /**
     * The participant who pays this expense.
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'payer_id');
    }

    /**
     * Register model hooks: validate the allocation on every save.
     */
    protected static function booted(): void
    {
        static::saving(fn (self $expense) => $expense->validateAllocation());
    }

    /**
     * Validate payer, split mode and shares, including the 100 % rule for percentage splits.
     *
     * @throws ValidationException When the allocation is incomplete or inconsistent.
     */
    public function validateAllocation(): void
    {
        Validator::make(['payer_id' => $this->payer_id, 'split_mode' => $this->split_mode, 'shares' => $this->shares], [
            'payer_id' => ['required', 'exists:participants,id'],
            'split_mode' => ['required', 'in:percentage,income,equal,personal'],
            'shares' => ['required', 'array', 'min:1'],
            'shares.*.participant_id' => ['required', 'integer', 'distinct', 'exists:participants,id'],
        ])->validate();

        if ($this->split_mode === 'personal' && count($this->shares) !== 1) {
            throw ValidationException::withMessages(['shares' => 'Persönliche Kosten brauchen genau einen Teilnehmer.']);
        }
        if ($this->split_mode === 'percentage') {
            $total = 0;
            foreach ($this->shares as $share) {
                $percentage = Money::cents($share['percentage'] ?? '', 'shares');
                if ($percentage > 10000) {
                    throw ValidationException::withMessages(['shares' => 'Ein Anteil darf höchstens 100 % betragen.']);
                }
                $total += $percentage;
            }
            if ($total !== 10000) {
                throw ValidationException::withMessages(['shares' => 'Die Anteile müssen zusammen genau 100 % ergeben.']);
            }
        }
    }
}
