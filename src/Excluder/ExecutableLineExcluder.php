<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Excluder;

use PhpParser\Node;

/**
 * @api
 */
interface ExecutableLineExcluder
{

    /**
     * Provided lines will be treated as not-executable
     * This can help you normalize/adjust better coverage percentage by for example:
     *    - Counting method calls as single line no matter if written as multiline or not
     *    - Excluding lines you don't want to cover (e.g. throw new LogicException() calls)
     */
    public function getExcludedLineRange(Node $node): ?ExcludedLineRange;

}
