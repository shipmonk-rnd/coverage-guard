<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Excluder;

use LogicException;

/**
 * @api
 */
final class ExcludedLineRange
{

    public function __construct(
        private readonly int $start,
        private readonly int $end,
    )
    {
        if ($start > $end) {
            throw new LogicException('Start must be less than or equal to end.');
        }
        if ($start < 1) {
            throw new LogicException('Start must be greater than or equal to 1.');
        }
        if ($end < 1) {
            throw new LogicException('End must be greater than or equal to 1.');
        }
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

}
