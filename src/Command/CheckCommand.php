<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\Arguments\CoverageFileCliArgument;
use ShipMonk\CoverageGuard\Cli\Options\ConfigCliOption;
use ShipMonk\CoverageGuard\Cli\Options\PatchCliOption;
use ShipMonk\CoverageGuard\Cli\Options\VerboseCliOption;
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
        #[CoverageFileCliArgument]
        string $coverageFile,

        #[PatchCliOption]
        ?string $patchFile = null,

        #[ConfigCliOption]
        ?string $configPath = null,

        #[VerboseCliOption]
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
