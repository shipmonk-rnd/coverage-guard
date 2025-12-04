<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\ClassMethod;

/**
 * Represents a non-empty method in class, trait or enum
 *
 * @api
 */
final class ClassMethodBlock extends CodeBlock
{

    public function __construct(
        private readonly ClassMethod $node,
        array $lines,
    )
    {
        parent::__construct($lines);
    }

    public function getNode(): ClassMethod
    {
        return $this->node;
    }

    public function getMethodName(): string
    {
        return $this->node->name->toString();
    }

}
