<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\Arguments\CoverageFileCliArgument;
use ShipMonk\CoverageGuard\Cli\Options\ConfigCliOption;
use ShipMonk\CoverageGuard\Cli\Options\PatchCliOption;
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
        #[CoverageFileCliArgument]
        string $coverageFile,

        #[PatchCliOption]
        string $patchPath,

        #[ConfigCliOption]
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
            $executableLinesMap = [];

            foreach ($fileCoverage->executableLines as $line) {
                $executableLinesMap[$line->lineNumber] = $line->hits > 0;
            }

            foreach ($changedLines as $lineNumber) {
                if (isset($executableLinesMap[$lineNumber])) {
                    $totalChangedLines++;
                    if ($executableLinesMap[$lineNumber]) {
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
        $this->stdoutPrinter->printLine("  Changed executable lines: {$totalChangedLines}"); // TODO excluders should be used
        $this->stdoutPrinter->printLine("  Covered lines:            <green>{$totalCoveredLines}</green>");
        $this->stdoutPrinter->printLine('  Uncovered lines:          <orange>' . ($totalChangedLines - $totalCoveredLines) . '</orange>');
        $this->stdoutPrinter->printLine("  Coverage:                 {$percentageFormatted}%");
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
