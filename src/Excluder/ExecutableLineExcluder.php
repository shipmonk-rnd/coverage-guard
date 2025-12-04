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
     *  e.g. to exclude lines you don't want to cover (like throw new LogicException() calls)
     */
    public function getExcludedLineRange(Node $node): ?ExcludedLineRange;

}
