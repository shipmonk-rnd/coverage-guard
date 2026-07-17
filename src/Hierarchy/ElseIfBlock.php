<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\ElseIf_;

/**
 * Represents an elseif statement block
 *
 * @api
 */
final class ElseIfBlock extends CodeBlock
{

    public function __construct(
        private readonly ElseIf_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): ElseIf_
    {
        return $this->node;
    }

}
