<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Report;

use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;

final class ReportedError
{

    public function __construct(
        public readonly CodeBlock $codeBlock,
        public readonly CoverageError $error,
    )
    {
    }

}
