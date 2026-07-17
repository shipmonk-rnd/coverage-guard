<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\PropertyHook;

/**
 * Represents a non-empty property hook body (PHP 8.4+)
 *
 * @api
 */
final class PropertyHookBlock extends CodeBlock
{

    public function __construct(
        private readonly PropertyHook $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): PropertyHook
    {
        return $this->node;
    }

    /**
     * Returns 'get' or 'set'
     */
    public function getHookName(): string
    {
        return $this->node->name->toString();
    }

}
