<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PhpParser\ParserFactory;
use ShipMonk\CoverageGuard\Cli\CliArgument;
use ShipMonk\CoverageGuard\Cli\CliOption;
use ShipMonk\CoverageGuard\CoverageGuard;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\PathHelper;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Report\ErrorFormatter;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Utils\PatchParser;

final class CheckCommand extends AbstractCommand
{

    public function __construct(
        private readonly string $cwd,
        private readonly Printer $printer,
        private readonly ConfigResolver $configResolver,
        private readonly PatchParser $patchParser,
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

        $pathHelper = new PathHelper($this->cwd);
        $formatter = new ErrorFormatter($pathHelper, $this->printer, $config);
        $phpParser = (new ParserFactory())->createForHostVersion();
        $guard = new CoverageGuard($this->printer, $phpParser, $config, $pathHelper, $this->patchParser);

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

}
