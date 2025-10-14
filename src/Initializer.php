<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use Throwable;
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

final class Initializer
{

    /**
     * @param list<string> $argv
     *
     * @throws ErrorException
     */
    public function initialize(
        string $cwd,
        array $argv,
    ): InitializationResult
    {
        if (!isset($argv[1])) {
            throw new ErrorException('Usage: vendor/bin/coverage-guard <clover-coverage.xml> [--patch <changes.patch>] [--config <coverage-guard.php>]');
        }

        $options = $this->parseOptions($argv, ['patch', 'config']);
        $coverageFile = $argv[1];
        $patchFile = $options['patch'] ?? null;
        $configFilePath = $options['config'] ?? null;

        if (!is_file($coverageFile)) {
            throw new ErrorException("Coverage file not found: {$coverageFile}");
        }

        if ($patchFile !== null) {
            if (!is_file($patchFile)) {
                throw new ErrorException("Patch file not found: {$patchFile}");
            }

            if (!str_ends_with($patchFile, '.patch')) {
                throw new ErrorException("Unknown patch filepath {$patchFile}, expecting .patch extension");
            }
        }

        if ($configFilePath !== null) {
            if (!is_file($configFilePath)) {
                throw new ErrorException("Provided config file not found: '{$configFilePath}'");
            }
            if (!str_ends_with($configFilePath, '.php')) {
                throw new ErrorException("Provided config file must have php extension: '{$configFilePath}'");
            }
        } else {
            $configFilePath = $cwd . '/coverage-guard.php';
        }

        $config = is_file($configFilePath)
            ? $this->loadConfig($configFilePath)
            : new Config();

        if ($config->getGitRoot() === null) {
            $detectedGitRoot = $this->detectGitRoot($cwd);

            if ($detectedGitRoot !== null) {
                $config->setGitRoot($detectedGitRoot);
            }
        }

        return new InitializationResult($coverageFile, $patchFile, $config);
    }

    /**
     * @param list<string> $argv
     * @param list<string> $optionNames
     * @return array<string, string|null>
     *
     * @throws ErrorException
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
                        throw new ErrorException("Option --{$optionName} requires a value");
                    }
                    $options[$optionName] = $next;
                    $i++;
                    continue 2;
                }
            }

            // If we reach here, it's an unknown option or argument
            if (str_starts_with($current, '-')) {
                throw new ErrorException("Unknown option: {$current}");
            }

            throw new ErrorException("Unknown argument: {$current}");
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

    /**
     * @throws ErrorException
     */
    private function loadConfig(string $configFile): Config
    {
        $loadedConfig = static function () use ($configFile): mixed {
            return require $configFile;
        };

        try {
            $result = $loadedConfig();
        } catch (Throwable $e) {
            $line = $e->getLine();
            $file = $e->getFile();
            $position = $file === $configFile ? "line $line" : "$file:$line";
            throw new ErrorException($e::class . " while loading config file '$configFile' at $position. " . $e->getMessage(), $e);
        }

        if (!$result instanceof Config) {
            throw new ErrorException("Config file '$configFile' must return an instance of " . Config::class);
        }

        return $result;
    }

}
