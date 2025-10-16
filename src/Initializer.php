<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Exception\HelpException;
use Throwable;
use function array_key_exists;
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
     * @throws HelpException
     */
    public function initialize(
        string $cwd,
        array $argv,
    ): InitializationResult
    {
        $usageMessage = "Usage: vendor/bin/coverage-guard <clover.xml>\n\n" .
            "Arguments:\n" .
            "  <white><clover.xml></white>                 Path to PHPUnit coverage file (.xml or .cov)\n\n" .
            "Options:\n" .
            "  <white>--patch</white> <changes.patch>      Check only changed files in the patch\n" .
            "  <white>--config</white> <config.php>        Path to config file (default: coverage-guard.php)\n" .
            "  <white>--debug</white>                      Show detailed processing information\n" .
            '  <white>--help</white>                       Show this help message';

        $argument = $argv[1] ?? null;

        if ($argument === null) {
            throw new ErrorException('Missing coverage file argument. Use e.g. <white>vendor/bin/coverage-guard clover.xml</white>');
        }

        if ($argument === '--help') {
            throw new HelpException($usageMessage);
        }

        $options = $this->parseOptions($argv, [
            'patch' => true,
            'config' => true,
            'debug' => false,
            'help' => false,
        ]);

        if (array_key_exists('help', $options)) {
            throw new HelpException($usageMessage);
        }

        $coverageFile = $argument;
        $patchFile = $options['patch'] ?? null;
        $configFilePath = $options['config'] ?? null;
        $debug = array_key_exists('debug', $options);

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

        $cliOptions = new CliOptions($coverageFile, $patchFile, $configFilePath, $debug);

        $config = is_file($configFilePath)
            ? $this->loadConfig($configFilePath)
            : new Config();

        if ($config->getGitRoot() === null) {
            $detectedGitRoot = $this->detectGitRoot($cwd);

            if ($detectedGitRoot !== null) {
                $config->setGitRoot($detectedGitRoot);
            }
        }

        return new InitializationResult($cliOptions, $config);
    }

    /**
     * @param list<string> $argv
     * @param array<string, bool> $optionConfig Map of option name => requires value
     * @return array<string, string|null>
     *
     * @throws ErrorException
     */
    private function parseOptions(
        array $argv,
        array $optionConfig,
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
            foreach ($optionConfig as $optionName => $requiresValue) {
                $prefix = '--' . $optionName . '=';
                if (str_starts_with($current, $prefix)) {
                    $options[$optionName] = substr($current, strlen($prefix));
                    continue 2;
                }
            }

            // Check for --option value syntax or boolean flags
            $next = $argv[$i + 1] ?? null;

            foreach ($optionConfig as $optionName => $requiresValue) {
                if ($current === '--' . $optionName) {
                    if (!$requiresValue) {
                        $options[$optionName] = null; // boolean flag is set
                        continue 2;
                    }
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
