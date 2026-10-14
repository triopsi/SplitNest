<?php

namespace Tests\Unit;

use App\Support\Money;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_cents_parses_comma_and_dot_decimals(): void
    {
        $this->assertSame(170125, Money::cents('1701,25'));
        $this->assertSame(170125, Money::cents('1701.25'));
        $this->assertSame(10000, Money::cents('100'));
        $this->assertSame(1050, Money::cents('10,5'));
        $this->assertSame(50, Money::cents('0,5'));
        $this->assertSame(999999999, Money::cents('9999999,99'));
        $this->assertSame(0, Money::cents('0'));
    }

    public function test_cents_trims_surrounding_whitespace(): void
    {
        $this->assertSame(10000, Money::cents('  100  '));
    }

    #[DataProvider('invalidAmounts')]
    public function test_cents_rejects_invalid_amounts(string $invalid): void
    {
        $this->expectException(ValidationException::class);
        Money::cents($invalid);
    }

    /**
     * Invalid amount inputs.
     *
     * @return array<string, array{string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'empty' => [''], 'whitespace' => ['  '], 'letters' => ['abc'],
            'three decimals' => ['1,234'], 'two dots' => ['1.2.3'], 'thousands separator' => ['1.234,56'],
            'negative' => ['-5'], 'trailing sign' => ['5-'], 'trailing dot' => ['10,'], 'too long' => ['12345678'],
        ];
    }

    public function test_cents_reports_the_given_field_name(): void
    {
        try {
            Money::cents('kaputt', 'shares');
            $this->fail('No exception thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('shares', $e->errors());
        }
    }

    public function test_decimal_formats_cents_as_plain_decimal(): void
    {
        $this->assertSame('1701.25', Money::decimal(170125));
        $this->assertSame('0.00', Money::decimal(0));
        $this->assertSame('0.05', Money::decimal(5));
    }

    public function test_format_outputs_german_currency(): void
    {
        $this->assertSame('1.701,25 €', Money::format(170125));
        $this->assertSame('0,00 €', Money::format(0));
        $this->assertSame('0,05 €', Money::format(5));
    }
}
