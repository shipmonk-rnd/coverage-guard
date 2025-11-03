<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\CliArgument;
use ShipMonk\CoverageGuard\Cli\CliOption;
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

    public function __construct(
        protected readonly Printer $printer,
    )
    {
    }

    /**
     * @throws ErrorException
     */
    public function __invoke(
        #[CliArgument('coverage-file', description: 'Path to PHPUnit coverage file (.xml or .cov)')]
        string $coverageFile,

        #[CliOption(description: 'Path to git diff result to check only changed code')]
        ?string $patch = null,

        #[CliOption(description: 'Path to config file')]
        ?string $config = null,
    ): int
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new ErrorException('Cannot determine current working directory');
        }

        $patchFile = $patch;
        $configPath = $config;

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
        if ($configPath !== null && $configPath !== '') {
            if (!is_file($configPath)) {
                throw new ErrorException("Provided config file not found: '{$configPath}'");
            }
            if (!str_ends_with($configPath, '.php')) {
                throw new ErrorException("Provided config file must have php extension: '{$configPath}'");
            }
        } else {
            $configPath = $cwd . '/coverage-guard.php';
        }

        $loadedConfig = is_file($configPath)
            ? $this->loadConfig($configPath)
            : new Config();

        // Auto-detect git root if needed
        if ($patchFile !== null && $loadedConfig->getGitRoot() === null) {
            $detectedGitRoot = $this->detectGitRoot($cwd);

            if ($detectedGitRoot !== null) {
                $loadedConfig->setGitRoot($detectedGitRoot);
            }
        }

        // Run coverage check
        $pathHelper = new PathHelper($cwd);
        $formatter = new ErrorFormatter($pathHelper, $this->printer, $loadedConfig);
        $guard = new CoverageGuard($loadedConfig, $this->printer, $pathHelper);

        $coverageReport = $guard->checkCoverage(
            $coverageFile,
            $patchFile,
            false,
        );

        return $formatter->formatReport($coverageReport);
    }

    public function getName(): string
    {
        return 'check';
    }

    public function getDescription(): string
    {
        return 'Enforce code coverage rules on your codebase';
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
