<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\If_;

/**
 * Represents an if statement block
 *
 * @api
 */
final class IfBlock extends CodeBlock
{

    public function __construct(
        private readonly If_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): If_
    {
        return $this->node;
    }

}
