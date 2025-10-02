<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use function count;
use function exec;
use function function_exists;
use function is_dir;
use function is_file;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

class Initializer
{

    /**
     * @param list<string> $argv
     */
    public function initialize(
        string $cwd,
        array $argv,
    ): InitializationResult
    {
        if (!isset($argv[1])) {
            throw new LogicException('Usage: vendor/bin/coverage-guard <clover-coverage.xml> [--patch <changes.patch>] [--config <config.php>]');
        }

        $options = $this->parseOptions($argv, ['patch', 'config']);
        $coverageFile = $argv[1];
        $patchFile = $options['patch'] ?? null;
        $configFilePath = $options['config'] ?? null;

        if (!is_file($coverageFile)) {
            throw new LogicException("Coverage file not found: {$coverageFile}");
        }

        if ($patchFile !== null && !is_file($patchFile)) {
            throw new LogicException("Patch file not found: {$patchFile}");
        }

        if ($configFilePath !== null) {
            if (!is_file($configFilePath)) {
                throw new LogicException("Provided config file not found: '{$configFilePath}'");
            }
            if (!str_ends_with($configFilePath, '.php')) {
                throw new LogicException("Provided config file must have php extension: '{$configFilePath}'");
            }
        } else {
            $configFilePath = $cwd . '/coverage-guard.php';
        }

        $config = is_file($configFilePath)
            ? $this->loadConfig($configFilePath)
            : new Config();

        $gitRoot = $this->detectGitRoot($cwd);
        if ($gitRoot !== null) {
            $config->setGitRoot($gitRoot);
        }

        return new InitializationResult($coverageFile, $patchFile, $config);
    }

    /**
     * @param list<string> $argv
     * @param list<string> $optionNames
     * @return array<string, string|null>
     */
    private function parseOptions(
        array $argv,
        array $optionNames,
    ): array
    {
        $options = [];
        $argc = count($argv);

        // Start from index 2, skipping argv[0] (script name) and argv[1] (coverage file)
        for ($i = 2; $i < $argc; $i++) {
            if (!isset($argv[$i])) {
                continue;
            }

            $current = $argv[$i];

            // Check for --option=value syntax
            foreach ($optionNames as $optionName) {
                $prefix = '--' . $optionName . '=';
                if (str_starts_with($current, $prefix)) {
                    $options[$optionName] = substr($current, strlen($prefix));
                    continue 2;
                }
            }

            // Check for --option value syntax
            $next = $argv[$i + 1] ?? null;

            foreach ($optionNames as $optionName) {
                if ($current === '--' . $optionName) {
                    if ($next === null) {
                        throw new LogicException("Option --{$optionName} requires a value");
                    }
                    $options[$optionName] = $next;
                    $i++;
                    continue 2;
                }
            }

            // If we reach here, it's an unknown option or argument
            if (str_starts_with($current, '-')) {
                throw new LogicException("Unknown option: {$current}");
            }

            throw new LogicException("Unknown argument: {$current}");
        }

        return $options;
    }

    private function detectGitRoot(string $cwd): ?string
    {
        if (is_dir($cwd . '/.git/')) {
            return $cwd;
        }

        if (!function_exists('exec')) {
            return null;
        }

        $output = [];
        $returnCode = 0;

        @exec('git rev-parse --show-toplevel 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0 && isset($output[0]) && strlen($output[0]) !== 0) {
            $gitRoot = trim($output[0]);
            // Validate the returned path is actually a directory
            if (is_dir($gitRoot)) {
                return $gitRoot;
            }
        }

        return null;
    }

    private function loadConfig(string $configFile): Config
    {
        $loadedConfig = static function () use ($configFile): mixed {
            return require $configFile;
        };

        $result = $loadedConfig();

        if (!$result instanceof Config) {
            throw new LogicException("Config file '$configFile' must return an instance of " . Config::class);
        }

        return $result;
    }

}
