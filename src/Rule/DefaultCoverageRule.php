<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;

final class DefaultCoverageRule implements CoverageRule
{

    public function inspect(
        CodeBlock $codeBlock,
        bool $patchMode,
    ): ?CoverageError
    {
        if (!$codeBlock instanceof ClassMethodBlock) {
            return null;
        }

        if (
            $codeBlock->isFullyUncovered()
            && $codeBlock->isFullyChanged()
            && $codeBlock->getExecutableLinesCount() > 5
        ) {
            $ref = "{$codeBlock->getClassName()}::{$codeBlock->getMethodName()}";
            $infix = $patchMode ? ' fully changed and' : '';

            return CoverageError::message("Method <white>$ref</white> is$infix fully untested.");
        }

        return null;
    }

}
