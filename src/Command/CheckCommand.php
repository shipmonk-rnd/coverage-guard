<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\CliArgument;
use ShipMonk\CoverageGuard\Cli\CliOption;
use ShipMonk\CoverageGuard\CoverageGuard;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Report\ErrorFormatter;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;

final class CheckCommand implements Command
{

    public function __construct(
        private readonly ConfigResolver $configResolver,
        private readonly CoverageGuard $coverageGuard,
        private readonly ErrorFormatter $errorFormatter,
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
        $config = $this->configResolver->resolveConfig($configPath);
        $coverageReport = $this->coverageGuard->checkCoverage($config, $coverageFile, $patchFile, $verbose);

        return $this->errorFormatter->formatReport($coverageReport, $config->getEditorUrl());
    }

    public function getName(): string
    {
        return 'check';
    }

    public function getDescription(): string
    {
        return 'Enforce code coverage rules on your codebase';
    }

}
