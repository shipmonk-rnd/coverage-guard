<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;

final class ReportedError
{

    public function __construct(
        public readonly CodeBlock $codeBlock,
        public readonly CoverageError $error,
    )
    {
    }

}
