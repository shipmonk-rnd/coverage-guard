<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Expr\Match_;

/**
 * Represents a match expression block (PHP 8.0+)
 *
 * @api
 */
final class MatchBlock extends CodeBlock
{

    public function __construct(
        private readonly Match_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): Match_
    {
        return $this->node;
    }

}
