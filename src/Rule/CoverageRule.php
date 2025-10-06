<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;

/**
 * @api
 */
interface CoverageRule
{

    public function inspect(
        CodeBlock $codeBlock,
        bool $patchMode,
    ): ?CoverageError;

}
