<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Excluder;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;

/**
 * Ensures only the first line is counted as executable for multiline calls, ignores others
 *
 * @api
 */
final class NormalizeMultilineCallsLineExcluder implements ExecutableLineExcluder
{

    public function getExcludedLineRange(Node $node): ?ExcludedLineRange
    {
        if (
            $node instanceof MethodCall
            || $node instanceof StaticCall
            || $node instanceof FuncCall
            || $node instanceof New_
        ) {
            $startLine = $node->getStartLine();
            $endLine = $node->getEndLine();

            if ($endLine > $startLine) {
                return new ExcludedLineRange($startLine + 1, $endLine);
            }
        }

        return null;
    }

}
