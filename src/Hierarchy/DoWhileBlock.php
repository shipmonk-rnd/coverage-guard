<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\Do_;

/**
 * Represents a do-while loop block
 *
 * @api
 */
final class DoWhileBlock extends CodeBlock
{

    public function __construct(
        private readonly Do_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): Do_
    {
        return $this->node;
    }

}
