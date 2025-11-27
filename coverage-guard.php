<?php declare(strict_types = 1);

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\AnalysisContext;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;

$config = new Config();
$config->addRule(new class implements CoverageRule {

    public function inspect(
        CodeBlock $codeBlock,
        AnalysisContext $context,
    ): ?CoverageError
    {
        if (!$codeBlock instanceof ClassMethodBlock) {
            return null;
        }

        $classReflection = $codeBlock->getMethodReflection()->getDeclaringClass();
        $coverage = $codeBlock->getCoveragePercentage();

        // @api class methods to have at least 50% coverage
        if ($this->isPublicApiClass($classReflection) && $codeBlock->getCoveragePercentage() < 50) {
            return CoverageError::create("Method <white>{$codeBlock->getMethodName()}</white> of @api class has only $coverage% coverage, expected at least 50%.");
        }

        return null;
    }

    /**
     * @param ReflectionClass<object> $classReflection
     */
    private function isPublicApiClass(ReflectionClass $classReflection): bool
    {
        return $classReflection->getDocComment() !== false && str_contains($classReflection->getDocComment(), '@api');
    }

});

$localConfig = __DIR__ . '/coverage-guard.local.php';
if (is_file($localConfig)) {
    require $localConfig; // handy for $config->setEditorUrl()
}

return $config;
