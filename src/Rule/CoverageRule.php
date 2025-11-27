<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;

/**
 * Implementors are expected to be passed to ShipMonk\CoverageGuard\Config::addRule() method
 *
 * @api
 */
interface CoverageRule
{

    public function inspect(
        CodeBlock $codeBlock,
        AnalysisContext $context,
    ): ?CoverageError;

}
