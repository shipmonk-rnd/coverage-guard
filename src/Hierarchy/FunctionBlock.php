<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\Function_;

/**
 * Represents a non-empty standalone function (not a class method)
 *
 * @api
 */
final class FunctionBlock extends CodeBlock
{

    public function __construct(
        private readonly Function_ $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): Function_
    {
        return $this->node;
    }

    public function getFunctionName(): string
    {
        return $this->node->name->toString();
    }

}
