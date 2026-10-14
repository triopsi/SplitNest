<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Participant extends Model
{
    protected $fillable = ['name', 'email', 'notes'];

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    protected static function booted(): void
    {
        // Participants are durable identities in historical reports and JSON allocations.
        static::deleting(fn () => throw ValidationException::withMessages(['participant' => 'Teilnehmer bleiben für historische Zuordnungen erhalten.']));
    }
}
