<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\Catch_;

/**
 * Represents a catch block
 *
 * @api
 */
final class CatchBlock extends CodeBlock
{

    public function __construct(
        private readonly Catch_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): Catch_
    {
        return $this->node;
    }

}
