<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * An immutable monthly settlement, frozen as a JSON snapshot.
 *
 * Once closed, a closing can neither be changed nor deleted, so PDFs and
 * reports always show the numbers as they were at closing time.
 *
 * @property string $month Closed month (Y-m).
 * @property array<string, mixed>|null $snapshot Frozen settlement report.
 * @property int|null $closed_by ID of the user who closed the month.
 * @property CarbonImmutable|null $closed_at
 */
class MonthlyClosing extends Model
{
    protected $fillable = ['month', 'snapshot', 'closed_by', 'closed_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'closed_at' => 'immutable_datetime'];
    }

    /**
     * Register model hooks: make closed months immutable and undeletable.
     *
     * @throws ValidationException When a closing is modified or deleted.
     */
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
