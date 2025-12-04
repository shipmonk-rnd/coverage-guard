<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Excluder;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Throw_ as OldThrow_;
use function in_array;

/**
 * Excludes lines for "throw new YourException()" statements
 *
 * @api
 */
final class IgnoreThrowNewExceptionLineExcluder implements ExecutableLineExcluder
{

    /**
     * @param list<string> $classNames
     */
    public function __construct(
        private readonly array $classNames,
    )
    {
    }

    public function getExcludedLineRange(Node $node): ?ExcludedLineRange
    {
        if (
            ($node instanceof Throw_ || $node instanceof OldThrow_)
            && $node->expr instanceof New_
            && $node->expr->class instanceof Name
            && in_array($node->expr->class->toString(), $this->classNames, true)
        ) {
            return new ExcludedLineRange($node->getStartLine(), $node->getEndLine());
        }
        return null;
    }

}
