<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\CliArgument;
use ShipMonk\CoverageGuard\Cli\CliOption;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function getcwd;
use function is_file;
use function number_format;
use function str_ends_with;

final class PatchCoverageCommand extends AbstractCommand
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

        #[CliOption(description: 'Patch file to analyze (required)')]
        string $patch,
    ): int
    {
        $patchFile = $patch;
        $cwd = getcwd();

        if ($cwd === false) {
            throw new ErrorException('Cannot determine current working directory');
        }

        if (!is_file($coverageFile)) {
            throw new ErrorException("Coverage file not found: {$coverageFile}");
        }

        if (!is_file($patchFile)) {
            throw new ErrorException("Patch file not found: {$patchFile}");
        }

        if (!str_ends_with($patchFile, '.patch') && !str_ends_with($patchFile, '.diff')) {
            throw new ErrorException("Unknown patch filepath {$patchFile}, expecting .patch or .diff extension");
        }

        // Extract coverage
        $extractor = $this->createExtractor($coverageFile);
        $coveragePerFile = [];
        foreach ($extractor->getCoverage($coverageFile) as $fileCoverage) {
            $realPath = $this->tryRealpath($fileCoverage->filePath);
            if ($realPath !== null) {
                $coveragePerFile[$realPath] = $fileCoverage;
            }
        }

        // Parse patch
        $gitRoot = $this->detectGitRoot($cwd);
        if ($gitRoot === null) {
            throw new ErrorException('In order to process patch files, you need to be inside git repository folder, install git or specify git root via config');
        }

        $changesPerFile = $this->getPatchChangedLines($patchFile, $gitRoot);

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

        // Output statistics
        if ($totalChangedLines === 0) {
            $this->printer->printLine('No executable lines found in patch.');
            $this->printer->printLine('');
            return 0;
        }

        $percentage = ($totalCoveredLines / $totalChangedLines) * 100;
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
