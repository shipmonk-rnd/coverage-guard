<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

use LogicException;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;

/**
 * @api
 */
final class EnforceCoverageForMethodsRule implements CoverageRule
{

    public function __construct(
        private readonly int $requiredCoveragePercentage = 1,
        private readonly int $minExecutableLines = 0,
        private readonly ?int $minMethodChangePercentage = null,
    )
    {
        if ($this->requiredCoveragePercentage < 0 || $this->requiredCoveragePercentage > 100) {
            throw new LogicException('Minimal required coverage percentage must be between 0 and 100');
        }
        if ($this->minMethodChangePercentage !== null && ($this->minMethodChangePercentage < 0 || $this->minMethodChangePercentage > 100)) {
            throw new LogicException('Minimal required method change percentage must be between 0 and 100');
        }
        if ($this->minExecutableLines < 0) {
            throw new LogicException('Minimal required executable lines must be at least 0');
        }
    }

    public function inspect(
        CodeBlock $codeBlock,
        InspectionContext $context,
    ): ?CoverageError
    {
        if (!$codeBlock instanceof ClassMethodBlock) {
            return null; // we only care about methods
        }

        $methodReflection = $context->getMethodReflection();
        if ($methodReflection === null) {
            return null; // e.g. anonymous class methods
        }

        if (
            $this->minMethodChangePercentage !== null
            && $codeBlock->getChangePercentage() < $this->minMethodChangePercentage
        ) {
            return null;
        }

        if (
            $codeBlock->getExecutableLinesCount() >= $this->minExecutableLines
            && $codeBlock->getCoveragePercentage() < $this->requiredCoveragePercentage
        ) {
            $className = $context->getClassName() ?? 'anonymous';
            $methodName = $codeBlock->getMethodName();
            $ref = "{$className}::{$methodName}";
            $coverage = $codeBlock->getCoveragePercentage();
            $currentString = $coverage === 0 ? 'no' : "only {$coverage}%";

            return CoverageError::create("Method <white>$ref</white> has $currentString coverage, expected at least $this->requiredCoveragePercentage%.");
        }

        return null;
    }

}
