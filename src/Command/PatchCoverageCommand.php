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

final class PatchCoverageCommand extends AbstractCommand
{

    public function __construct(
        private readonly Printer $printer,
        private readonly PatchParser $patchParser,
        private readonly ConfigResolver $configResolver,
        private readonly CoverageProvider $extractorFactory,
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

        $coveragePerFile = $this->extractorFactory->getCoverage($config, $coverageFile);
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

        $this->printer->printLine('Patch Coverage Statistics:');
        $this->printer->printLine('');
        $this->printer->printLine("  Changed executable lines: <white>{$totalChangedLines}</white>");
        $this->printer->printLine("  Covered lines:            <green>{$totalCoveredLines}</green>");
        $this->printer->printLine('  Uncovered lines:          <orange>' . ($totalChangedLines - $totalCoveredLines) . '</orange>');
        $this->printer->printLine("  Coverage:                 <white>{$percentageFormatted}%</white>");
        $this->printer->printLine('');

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
