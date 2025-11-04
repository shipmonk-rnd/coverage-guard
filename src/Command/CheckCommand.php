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
use function is_file;
use function str_ends_with;

final class CheckCommand extends AbstractCommand
{

    public function __construct(
        private readonly string $cwd,
        private readonly Printer $printer,
    )
    {
    }

    /**
     * @throws ErrorException
     */
    public function __invoke(
        #[CliArgument(description: 'Path to PHPUnit coverage file (.xml or .cov)')]
        string $coverageFile,

        #[CliOption(name: 'patch', description: 'Path to git diff result to check only changed code')]
        ?string $patchFile = null,

        #[CliOption(name: 'config', description: 'Path to PHP config file')]
        ?string $configPath = null,

        #[CliOption(description: 'Print all processed files')]
        bool $verbose = false,
    ): int
    {
        $this->validateCoverageFile($coverageFile);
        $this->validatePatchFile($patchFile);

        $resolvedConfigPath = $this->resolveConfigPath($configPath);
        $config = $this->resolveConfig($resolvedConfigPath);

        $this->autoDetectGitRoot($config, $patchFile);

        $pathHelper = new PathHelper($this->cwd);
        $formatter = new ErrorFormatter($pathHelper, $this->printer, $config);
        $guard = new CoverageGuard($config, $this->printer, $pathHelper);

        $coverageReport = $guard->checkCoverage($coverageFile, $patchFile, $verbose);

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
    private function validateCoverageFile(string $coverageFile): void
    {
        if (!is_file($coverageFile)) {
            throw new ErrorException("Coverage file not found: {$coverageFile}");
        }
    }

    /**
     * @throws ErrorException
     */
    private function validatePatchFile(?string $patchFile): void
    {
        if ($patchFile === null) {
            return;
        }

        if (!is_file($patchFile)) {
            throw new ErrorException("Patch file not found: {$patchFile}");
        }

        if (!str_ends_with($patchFile, '.patch') && !str_ends_with($patchFile, '.diff')) {
            throw new ErrorException("Unknown patch filepath {$patchFile}, expecting .patch or .diff extension");
        }
    }

    /**
     * @throws ErrorException
     */
    private function resolveConfigPath(?string $configPath): string
    {
        if ($configPath !== null) {
            if (!is_file($configPath)) {
                throw new ErrorException("Provided config file not found: '{$configPath}'");
            }

            if (!str_ends_with($configPath, '.php')) {
                throw new ErrorException("Provided config file must have php extension: '{$configPath}'");
            }

            return $configPath;
        }

        return $this->cwd . '/coverage-guard.php';
    }

    /**
     * @throws ErrorException
     */
    private function resolveConfig(string $configPath): Config
    {
        return is_file($configPath)
            ? $this->loadConfig($configPath)
            : new Config();
    }

    /**
     * @throws ErrorException
     */
    private function autoDetectGitRoot(
        Config $config,
        ?string $patchFile,
    ): void
    {
        if ($patchFile === null || $config->getGitRoot() !== null) {
            return;
        }

        $detectedGitRoot = $this->detectGitRoot($this->cwd);

        if ($detectedGitRoot !== null) {
            $config->setGitRoot($detectedGitRoot);
        }
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
