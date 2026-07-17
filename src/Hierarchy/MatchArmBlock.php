<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\MatchArm;

/**
 * Represents a single arm of a match expression (PHP 8.0+)
 *
 * @api
 */
final class MatchArmBlock extends CodeBlock
{

    public function __construct(
        private readonly MatchArm $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): MatchArm
    {
        return $this->node;
    }

}
