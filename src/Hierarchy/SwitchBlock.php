<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\Switch_;

/**
 * Represents a switch statement block
 *
 * @api
 */
final class SwitchBlock extends CodeBlock
{

    public function __construct(
        private readonly Switch_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): Switch_
    {
        return $this->node;
    }

}
