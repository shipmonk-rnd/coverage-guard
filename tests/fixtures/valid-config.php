<?php declare(strict_types = 1);

use ShipMonk\CoverageGuard\Config;

$config = new Config();
$config->setGitRoot(__DIR__);
return $config;
