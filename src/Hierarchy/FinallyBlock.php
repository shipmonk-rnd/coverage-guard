<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\Finally_;

/**
 * Represents a finally block
 *
 * @api
 */
final class FinallyBlock extends CodeBlock
{

    public function __construct(
        private readonly Finally_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): Finally_
    {
        return $this->node;
    }

}
