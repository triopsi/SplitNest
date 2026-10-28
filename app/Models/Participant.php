<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * A household member who shares incomes and expenses.
 *
 * Participants are durable identities: they can never be deleted, because
 * historical closings and JSON allocations keep referring to their IDs.
 *
 * @property bool $only_own_report Limit emailed statements to personal costs and payments.
 */
class Participant extends Model
{
    protected $fillable = ['name', 'email', 'notes', 'only_own_report'];

    protected $attributes = ['only_own_report' => false];

    /** Cast the recipient's statement visibility preference.
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['only_own_report' => 'boolean'];
    }

    /**
     * All incomes of this participant.
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /**
     * Register model hooks: block deletion to protect historical data.
     */
    protected static function booted(): void
    {
        static::deleting(fn () => throw ValidationException::withMessages(['participant' => __('participants.delete_protected')]));
    }
}
