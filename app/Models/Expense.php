<?php

namespace App\Models;

use App\Models\Concerns\HasSchedule;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Expense extends Model
{
    use HasSchedule;

    public const MODES = ['percentage' => 'Feste Prozente', 'income' => 'Nach Einkommen', 'equal' => 'Gleiche Anteile', 'personal' => 'Persönlich (100 %)'];

    protected $fillable = ['payer_id', 'name', 'amount', 'amount_cents', 'split_mode', 'shares', 'start_month', 'end_month', 'interval_months', 'notes'];

    protected $appends = ['amount'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'interval_months' => 'integer', 'shares' => 'array'];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'payer_id');
    }

    protected static function booted(): void
    {
        static::saving(fn (self $expense) => $expense->validateAllocation());
    }

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
