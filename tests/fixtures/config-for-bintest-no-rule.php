<?php declare(strict_types = 1);

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;

$config = new Config();
$config->setGitRoot(__DIR__ . '/../..');

return $config;
