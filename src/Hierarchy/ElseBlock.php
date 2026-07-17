<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\Else_;

/**
 * Represents an else statement block
 *
 * @api
 */
final class ElseBlock extends CodeBlock
{

    public function __construct(
        private readonly Else_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): Else_
    {
        return $this->node;
    }

}
