<?php declare(strict_types = 1);

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Excluder\IgnoreThrowNewExceptionLineExcluder;

$config = new Config();
$config->addExecutableLineExcluder(new IgnoreThrowNewExceptionLineExcluder([LogicException::class]));

return $config;
