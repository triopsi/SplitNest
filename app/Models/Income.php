<?php

namespace App\Models;

use App\Models\Concerns\HasSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    use HasSchedule;

    protected $fillable = ['participant_id', 'name', 'amount', 'amount_cents', 'start_month', 'end_month', 'interval_months'];

    protected $appends = ['amount'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'interval_months' => 'integer'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
