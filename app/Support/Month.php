<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Format stored calendar months consistently in the active application language. */
final class Month
{
    /** Display a canonical year-month value as a full month name and year. */
    public static function format(string $month): string
    {
        return Carbon::createFromFormat('!Y-m', $month)->locale(app()->getLocale())->translatedFormat('F Y');
    }
}
