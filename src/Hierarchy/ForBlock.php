<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\For_;

/**
 * Represents a for loop block
 *
 * @api
 */
final class ForBlock extends CodeBlock
{

    public function __construct(
        private readonly For_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): For_
    {
        return $this->node;
    }

}
