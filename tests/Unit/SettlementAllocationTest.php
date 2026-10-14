<?php

namespace Tests\Unit;

use App\Services\SettlementCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SettlementAllocationTest extends TestCase
{
    private SettlementCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SettlementCalculator;
    }

    public function test_even_split_without_remainder(): void
    {
        $this->assertSame([1 => 5000, 2 => 5000], $this->calculator->allocate(10000, [1 => 1, 2 => 1]));
    }

    public function test_remainder_goes_to_lowest_ids_first(): void
    {
        $this->assertSame([1 => 5001, 2 => 5000], $this->calculator->allocate(10001, [1 => 1, 2 => 1]));
    }

    public function test_weighted_split_is_proportional(): void
    {
        $this->assertSame([1 => 2500, 2 => 7500], $this->calculator->allocate(10000, [1 => 1, 2 => 3]));
    }

    public function test_single_cent_tie_break_is_stable_by_id(): void
    {
        $this->assertSame([3 => 1, 5 => 0, 8 => 0], $this->calculator->allocate(1, [5 => 1, 3 => 1, 8 => 1]));
    }

    public function test_zero_amount_allocates_nothing(): void
    {
        $this->assertSame([1 => 0, 2 => 0], $this->calculator->allocate(0, [1 => 1, 2 => 1]));
    }

    public function test_allocations_always_sum_to_the_amount(): void
    {
        foreach ([1, 7, 100, 10001, 123456789] as $amount) {
            $result = $this->calculator->allocate($amount, [1 => 55, 2 => 30, 3 => 15]);
            $this->assertSame($amount, array_sum($result), "Amount {$amount} not conserved");
        }
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->allocate(-1, [1 => 1]);
    }

    public function test_empty_weights_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->allocate(100, []);
    }

    public function test_all_zero_weights_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->allocate(100, [1 => 0, 2 => 0]);
    }

    public function test_negative_weights_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->allocate(100, [1 => -5, 2 => 10]);
    }
}
