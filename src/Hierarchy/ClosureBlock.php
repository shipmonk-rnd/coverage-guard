<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Expr\Closure;

/**
 * Represents a closure (anonymous function) block
 *
 * @api
 */
final class ClosureBlock extends CodeBlock
{

    public function __construct(
        private readonly Closure $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): Closure
    {
        return $this->node;
    }

}
