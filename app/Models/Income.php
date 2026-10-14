<?php

namespace App\Models;

use App\Models\Concerns\HasSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A participant's recurring or one-off income, used to weigh cost splits.
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
 */
class Income extends Model
{
    use HasSchedule;

    protected $fillable = ['participant_id', 'name', 'amount', 'amount_cents', 'start_month', 'end_month', 'interval_months'];

    protected $appends = ['amount'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'interval_months' => 'integer'];
    }

    /**
     * The participant this income belongs to.
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
