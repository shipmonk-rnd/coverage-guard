<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\Foreach_;

/**
 * Represents a foreach loop block
 *
 * @api
 */
final class ForeachBlock extends CodeBlock
{

    public function __construct(
        private readonly Foreach_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): Foreach_
    {
        return $this->node;
    }

}
