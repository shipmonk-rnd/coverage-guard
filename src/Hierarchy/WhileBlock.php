<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\While_;

/**
 * Represents a while loop block
 *
 * @api
 */
final class WhileBlock extends CodeBlock
{

    public function __construct(
        private readonly While_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): While_
    {
        return $this->node;
    }

}
