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

    /**
     * @param bool $patchMode True when --patch option is used (and only changed code blocks are analyzed)
     */
    public function inspect(
        CodeBlock $codeBlock,
        bool $patchMode,
    ): ?CoverageError;

}
