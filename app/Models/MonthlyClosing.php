<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MonthlyClosing extends Model
{
    protected $fillable = ['month', 'snapshot', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'closed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $closing): void {
            if ($closing->getOriginal('closed_at')) {
                throw ValidationException::withMessages(['month' => 'Dieser Monat ist abgeschlossen und unveränderlich.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['month' => 'Monatsabschlüsse dürfen nicht gelöscht werden.']));
    }
}
