<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Coverage;

final class ExecutableLine
{

    public function __construct(
        public readonly int $lineNumber,
        public readonly int $hits,
        public readonly ?string $infix = null,
    )
    {
    }

}
