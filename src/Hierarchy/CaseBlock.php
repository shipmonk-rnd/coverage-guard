<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\Case_;

/**
 * Represents a case block within a switch statement
 *
 * @api
 */
final class CaseBlock extends CodeBlock
{

    public function __construct(
        private readonly Case_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): Case_
    {
        return $this->node;
    }

}
