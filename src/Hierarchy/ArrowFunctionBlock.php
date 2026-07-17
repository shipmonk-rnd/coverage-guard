<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Expr\ArrowFunction;

/**
 * Represents an arrow function block (PHP 7.4+)
 *
 * @api
 */
final class ArrowFunctionBlock extends CodeBlock
{

    public function __construct(
        private readonly ArrowFunction $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): ArrowFunction
    {
        return $this->node;
    }

}
