<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Excluder;

use LogicException;
use PHPUnit\Framework\TestCase;

final class ExcludedLineRangeTest extends TestCase
{

    public function testGetters(): void
    {
        $range = new ExcludedLineRange(2, 5);

        self::assertSame(2, $range->getStart());
        self::assertSame(5, $range->getEnd());
    }

    public function testThrowsWhenStartExceedsEnd(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Start must be less than or equal to end.');

        new ExcludedLineRange(5, 2);
    }

    public function testThrowsWhenStartBelowOne(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Start must be greater than or equal to 1.');

        new ExcludedLineRange(0, 2);
    }

}
