<?php declare(strict_types = 1);

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;

$config = new Config();
$config->setGitRoot(__DIR__ . '/../..');
$config->addRule(new class implements CoverageRule {

    public function inspect(CodeBlock $codeBlock, bool $patchMode,): ?CoverageError
    {
        if (!$codeBlock->isCoveredAtLeastByPercent(100)) {
            return CoverageError::message('We need 100% coverage!');
        }
        return null;
    }
});

return $config;
