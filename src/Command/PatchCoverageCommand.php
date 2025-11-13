<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\CliArgument;
use ShipMonk\CoverageGuard\Cli\CliOption;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Utils\PatchParser;
use function number_format;

final class PatchCoverageCommand implements Command
{

    public function __construct(
        private readonly Printer $stdoutPrinter,
        private readonly PatchParser $patchParser,
        private readonly ConfigResolver $configResolver,
        private readonly CoverageProvider $coverageProvider,
    )
    {
    }

    /**
     * @throws ErrorException
     */
    public function __invoke(
        #[CliArgument('coverage-file', description: 'Path to PHPUnit coverage file (.xml or .cov)')]
        string $coverageFile,

        #[CliOption(name: 'patch', description: 'Patch file to analyze')]
        string $patchPath,

        #[CliOption(name: 'config', description: 'Path to PHP config file')]
        ?string $configPath = null,
    ): int
    {
        $config = $this->configResolver->resolveConfig($configPath);

        $coveragePerFile = $this->coverageProvider->getCoverage($config, $coverageFile);
        $changesPerFile = $this->patchParser->getPatchChangedLines($patchPath, $config);

        // Calculate coverage for changed lines
        $totalChangedLines = 0;
        $totalCoveredLines = 0;

        foreach ($changesPerFile as $file => $changedLines) {
            if (!isset($coveragePerFile[$file])) {
                continue; // File not in coverage report
            }

            $fileCoverage = $coveragePerFile[$file];
            $coveredLinesMap = [];

            foreach ($fileCoverage->executableLines as $line) {
                $coveredLinesMap[$line->lineNumber] = $line->hits > 0;
            }

            foreach ($changedLines as $lineNumber) {
                if (isset($coveredLinesMap[$lineNumber])) {
                    $totalChangedLines++;
                    if ($coveredLinesMap[$lineNumber]) {
                        $totalCoveredLines++;
                    }
                }
            }
        }

        if ($totalChangedLines === 0) {
            $percentage = 0;
        } else {
            $percentage = ($totalCoveredLines / $totalChangedLines) * 100;
        }

        $percentageFormatted = number_format($percentage, 2);

        $this->stdoutPrinter->printLine('Patch Coverage Statistics:');
        $this->stdoutPrinter->printLine('');
        $this->stdoutPrinter->printLine("  Changed executable lines: <white>{$totalChangedLines}</white>");
        $this->stdoutPrinter->printLine("  Covered lines:            <green>{$totalCoveredLines}</green>");
        $this->stdoutPrinter->printLine('  Uncovered lines:          <orange>' . ($totalChangedLines - $totalCoveredLines) . '</orange>');
        $this->stdoutPrinter->printLine("  Coverage:                 <white>{$percentageFormatted}%</white>");
        $this->stdoutPrinter->printLine('');

        return 0;
    }

    public function getName(): string
    {
        return 'patch-coverage';
    }

    public function getDescription(): string
    {
        return 'Calculate coverage percentage for lines changed in a patch';
    }

}
