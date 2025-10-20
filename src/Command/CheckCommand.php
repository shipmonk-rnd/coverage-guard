<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\CoverageGuard;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\PathHelper;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Report\ErrorFormatter;
use Throwable;
use function getcwd;
use function is_file;
use function str_ends_with;

final class CheckCommand extends AbstractCommand
{

    public function getName(): string
    {
        return 'check';
    }

    public function getDescription(): string
    {
        return 'Enforce code coverage rules on your codebase';
    }

    public function getArguments(): array
    {
        return [
            new Argument('coverage-file', 'Path to PHPUnit coverage file (.xml or .cov)'),
        ];
    }

    public function getOptions(): array
    {
        return [
            new Option('patch', 'Path to git diff result to check only changed code', requiresValue: true),
            new Option('config', 'Path to config file', requiresValue: true),
        ];
    }

    /**
     * @throws ErrorException
     */
    protected function run(Printer $printer): int
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new ErrorException('Cannot determine current working directory');
        }

        $coverageFile = $this->getArgument('coverage-file');
        $patchFile = $this->getRequiredStringOption('patch');
        $configFilePath = $this->getRequiredStringOption('config');

        // Validate coverage file
        if (!is_file($coverageFile)) {
            throw new ErrorException("Coverage file not found: {$coverageFile}");
        }

        // Validate patch file
        if ($patchFile !== null && $patchFile !== '') {
            if (!is_file($patchFile)) {
                throw new ErrorException("Patch file not found: {$patchFile}");
            }

            if (!str_ends_with($patchFile, '.patch') && !str_ends_with($patchFile, '.diff')) {
                throw new ErrorException("Unknown patch filepath {$patchFile}, expecting .patch or .diff extension");
            }
        } else {
            $patchFile = null;
        }

        // Load config
        if ($configFilePath !== null && $configFilePath !== '') {
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

        // Auto-detect git root if needed
        if ($patchFile !== null && $config->getGitRoot() === null) {
            $detectedGitRoot = $this->detectGitRoot($cwd);

            if ($detectedGitRoot !== null) {
                $config->setGitRoot($detectedGitRoot);
            }
        }

        // Run coverage check
        $pathHelper = new PathHelper($cwd);
        $formatter = new ErrorFormatter($pathHelper, $printer, $config);
        $guard = new CoverageGuard($config, $printer, $pathHelper);

        $coverageReport = $guard->checkCoverage(
            $coverageFile,
            $patchFile,
            false,
        );

        return $formatter->formatReport($coverageReport);
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
