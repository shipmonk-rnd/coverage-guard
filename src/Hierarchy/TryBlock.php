<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Stmt\TryCatch;

/**
 * Represents a try block
 *
 * @api
 */
final class TryBlock extends CodeBlock
{

    public function __construct(
        private readonly TryCatch $node,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($lines, $parent);
    }

    public function getNode(): TryCatch
    {
        return $this->node;
    }

}
