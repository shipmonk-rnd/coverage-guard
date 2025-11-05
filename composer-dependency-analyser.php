<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();
$config->ignoreErrorsOnPackage('phpunit/php-code-coverage', [ErrorType::DEV_DEPENDENCY_IN_PROD]); // optional dependency to load .cov files
$config->ignoreErrorsOnPackage('sebastian/diff', [ErrorType::DEV_DEPENDENCY_IN_PROD]); // optional dependency to parse patch files
$config->ignoreErrorsOnExtension('ext-tokenizer', [ErrorType::SHADOW_DEPENDENCY]); // optional dependency to have syntax highlighting
$config->ignoreErrorsOnExtensionAndPath('ext-dom', __DIR__ . '/src/Writer', [ErrorType::SHADOW_DEPENDENCY]); // optional dependency to write XMLs (convert / merge)
$config->ignoreErrorsOnExtensions(['ext-simplexml', 'ext-libxml'], [ErrorType::SHADOW_DEPENDENCY]); // optional dependency to load clover/cobertura files

return $config;
