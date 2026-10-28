<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;

/** An earmarked pool whose unspent funds remain with their income recipients. */
class Budget extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /** Validate the budget name before persisting changes. */
    protected static function booted(): void
    {
        static::saving(function (self $budget): void {
            Validator::make($budget->getAttributes(), ['name' => ['required', 'string', 'max:255']])->validate();
        });
    }

    /** Receipts reserved for this purpose. */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /** Expenses funded from this pool before allocating any uncovered remainder. */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
