<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Converts between user-entered decimal amounts and integer cents.
 *
 * All monetary values are stored as integer cents to avoid float errors.
 */
final class Money
{
    /**
     * Parse a user-entered decimal amount (e.g. "1701,25") into integer cents.
     *
     * Accepts both comma and dot as decimal separator; no thousands separators.
     *
     * @throws ValidationException When the value is not a positive amount with at most two decimals.
     */
    public static function cents(mixed $amount, string $field = 'amount'): int
    {
        $value = str_replace(',', '.', trim((string) $amount));
        if (! preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages([$field => 'Bitte einen positiven Betrag mit maximal zwei Nachkommastellen eingeben (ohne Tausenderpunkte).']);
        }
        [$euros, $cents] = array_pad(explode('.', $value), 2, '0');

        return ((int) $euros * 100) + (int) str_pad($cents, 2, '0');
    }

    /**
     * Format integer cents as a plain decimal string (e.g. 170125 => "1701.25").
     */
    public static function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    /**
     * Format integer cents as a German currency string (e.g. "1.701,25 €").
     */
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }
}
