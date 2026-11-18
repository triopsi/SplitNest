<?php

namespace App\Models;

use App\Services\ArchiveParticipant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A household member who shares incomes and expenses.
 *
 * Unused participants can be deleted; linked entries and saved reports retain their identities.
 *
 * @property bool $only_own_report Limit emailed statements to personal costs and payments.
 * @property string|null $archived_from_month First month excluded from active planning.
 */
class Participant extends Model
{
    protected $fillable = ['name', 'email', 'notes', 'only_own_report', 'account_balance', 'account_balance_month', 'account_balance_cents'];

    protected $attributes = ['only_own_report' => false];

    protected $appends = ['account_balance'];

    /** Cast the recipient's statement visibility preference.
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['only_own_report' => 'boolean', 'account_balance_cents' => 'integer'];
    }

    /** Convert signed decimal input without allowing floating point rounding.
     * @return Attribute<string|null, mixed>
     */
    protected function accountBalance(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->account_balance_cents === null ? null : ($this->account_balance_cents < 0 ? '-' : '').Money::decimal(abs($this->account_balance_cents)),
            set: fn (mixed $value): array => ['account_balance_cents' => $value === null || $value === '' ? null : (str_starts_with(trim((string) $value), '-') ? -1 : 1) * Money::cents(preg_replace('/^-/', '', trim((string) $value)), 'account_balance')],
        );
    }

    /** Validate paired reference values, including zero and negative balances. */
    public function validateAccountBalance(): void
    {
        Validator::make($this->getAttributes(), [
            'account_balance_month' => ['nullable', 'required_with:account_balance_cents', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:2100-12'],
            'account_balance_cents' => ['nullable', 'required_with:account_balance_month', 'integer', 'between:-999999999,999999999'],
        ])->validate();
    }

    /**
     * All incomes of this participant.
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /**
     * Protect references even when deletion happens outside the management table.
     */
    protected static function booted(): void
    {
        static::saving(function (self $participant): void {
            $participant->validateAccountBalance();
            if ($participant->isDirty('archived_from_month') && $participant->archived_from_month !== null) {
                app(ArchiveParticipant::class)->validate($participant, $participant->archived_from_month);
            }
        });
        static::deleting(function (self $participant): void {
            if ($reason = $participant->deletionBlockReason()) {
                throw ValidationException::withMessages(['participant' => $reason]);
            }
        });
    }

    /** Keep earlier planning months available while excluding archived identities from later calculations.
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiveInMonth(Builder $query, string $month): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query->whereNull('archived_from_month')->orWhere('archived_from_month', '>', $month));
    }

    /** Reject new or extended scheduled assignments that would outlive this identity's archive boundary. */
    public function validateSchedule(Expense|Income $entry, string $field): void
    {
        if ($this->archived_from_month && $entry->nextBillingMonth($this->archived_from_month) !== null) {
            throw ValidationException::withMessages([$field => __('participants.archive.assignment_invalid', ['name' => $this->name])]);
        }
    }

    /** Explain which current or historical references prevent removal, including JSON-only allocations. */
    public function deletionBlockReason(): ?string
    {
        if (Income::where('participant_id', $this->id)->orWhere('sender_id', $this->id)->exists()) {
            return __('participants.delete_has_incomes');
        }
        if (Expense::where('payer_id', $this->id)->exists()
            || Expense::select('shares')->cursor()->contains(fn (Expense $expense): bool => collect($expense->shares)->contains(fn (array $share): bool => (int) ($share['participant_id'] ?? 0) === $this->id))) {
            return __('participants.delete_has_expenses');
        }
        if (MonthlyClosing::whereNotNull('snapshot')->select('snapshot')->cursor()->contains(fn (MonthlyClosing $closing): bool => collect($closing->snapshot['participants'] ?? [])->contains(fn (array $person): bool => (int) ($person['id'] ?? 0) === $this->id))) {
            return __('participants.delete_has_closings');
        }

        return null;
    }
}
