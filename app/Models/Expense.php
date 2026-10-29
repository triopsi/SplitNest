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
 * @property int|null $budget_id Pool used before splitting uncovered costs.
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

    /** Available cost-splitting strategies (keys, see {@see Expense::modeLabels()}). */
    public const MODE_KEYS = ['percentage', 'income', 'equal', 'personal'];

    protected $fillable = ['payer_id', 'name', 'amount', 'amount_cents', 'split_mode', 'shares', 'start_month', 'end_month', 'interval_months', 'notes', 'budget_id', 'price_changes'];

    protected $appends = ['amount'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'interval_months' => 'integer', 'shares' => 'array', 'price_changes' => 'array'];
    }

    /**
     * The participant who pays this expense.
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'payer_id');
    }

    /** The earmarked pool used before splitting the remaining cost. */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * The split strategies as translated UI labels (key => label).
     *
     * @return array<string, string>
     */
    public static function modeLabels(): array
    {
        return [
            'percentage' => __('expenses.modes.percentage'),
            'income' => __('expenses.modes.income'),
            'equal' => __('expenses.modes.equal'),
            'personal' => __('expenses.modes.personal'),
        ];
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
        $this->validatePriceChanges();
        Validator::make(['payer_id' => $this->payer_id, 'split_mode' => $this->split_mode, 'shares' => $this->shares, 'budget_id' => $this->budget_id], [
            'budget_id' => ['nullable', 'exists:budgets,id'],
            'payer_id' => ['required', 'exists:participants,id'],
            'split_mode' => ['required', 'in:percentage,income,equal,personal'],
            'shares' => ['required', 'array', 'min:1'],
            'shares.*.participant_id' => ['required', 'integer', 'distinct', 'exists:participants,id'],
        ])->validate();
        foreach (Participant::whereIn('id', array_unique([$this->payer_id, ...array_column($this->shares, 'participant_id')]))->get() as $participant) {
            $participant->validateSchedule($this, 'shares');
        }

        if ($this->split_mode === 'personal' && count($this->shares) !== 1) {
            throw ValidationException::withMessages(['shares' => __('expenses.validation.personal_one')]);
        }
        if ($this->split_mode === 'percentage') {
            $total = 0;
            foreach ($this->shares as $share) {
                $percentage = Money::cents($share['percentage'] ?? '', 'shares');
                if ($percentage > 10000) {
                    throw ValidationException::withMessages(['shares' => __('expenses.validation.max_100')]);
                }
                $total += $percentage;
            }
            if ($total !== 10000) {
                throw ValidationException::withMessages(['shares' => __('expenses.validation.sum_100')]);
            }
        }
    }

    /** Validate unique calendar-month changes while leaving the original billing rhythm untouched. */
    public function validatePriceChanges(): void
    {
        Validator::make(['start_month' => $this->start_month, 'end_month' => $this->end_month, 'price_changes' => $this->price_changes], [
            'price_changes' => ['nullable', 'array', 'list', 'max:120'],
            'price_changes.*' => ['array:month,amount'],
            'price_changes.*.month' => ['required', 'date_format:Y-m', 'distinct', 'after_or_equal:start_month', ...($this->end_month ? ['before_or_equal:end_month'] : []), ...($this->interval_months === 0 ? ['before_or_equal:start_month'] : [])],
            'price_changes.*.amount' => ['required', 'regex:/^\d{1,7}([.,]\d{1,2})?$/D'],
        ])->validate();
    }

    /** Resolve the most recent effective price without mutating the original amount. */
    public function amountForMonth(string $month): int
    {
        $change = collect($this->price_changes ?? [])->filter(fn (array $change): bool => $change['month'] <= $month)->sortByDesc('month')->first();

        return $change ? Money::cents($change['amount']) : $this->amount_cents;
    }
}
