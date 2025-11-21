<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Coverage;

use LogicException;

final class ExecutableLine
{

    public function __construct(
        public readonly int $lineNumber,
        public readonly int $hits,
    )
    {
        if ($hits < 0) {
            throw new LogicException('Hits must be greater than or equal to 0.');
        }

        if ($lineNumber <= 0) {
            throw new LogicException('Line number must be greater than or equal to 1.');
        }
    }

}
