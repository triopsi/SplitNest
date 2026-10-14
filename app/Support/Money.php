<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class Money
{
    public static function cents(mixed $amount, string $field = 'amount'): int
    {
        $value = str_replace(',', '.', trim((string) $amount));
        if (! preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages([$field => 'Bitte einen positiven Betrag mit maximal zwei Nachkommastellen eingeben (ohne Tausenderpunkte).']);
        }
        [$euros, $cents] = array_pad(explode('.', $value), 2, '0');

        return ((int) $euros * 100) + (int) str_pad($cents, 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }
}
