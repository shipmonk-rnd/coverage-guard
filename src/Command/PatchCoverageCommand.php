<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function getcwd;
use function is_file;
use function number_format;
use function str_ends_with;

final class PatchCoverageCommand extends AbstractCommand
{

    public function getName(): string
    {
        return 'patch-coverage';
    }

    public function getDescription(): string
    {
        return 'Calculate coverage percentage for lines changed in a patch';
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
            new Option('patch', 'Patch file to analyze (required)', requiresValue: true),
        ];
    }

    /**
     * @throws ErrorException
     */
    protected function run(Printer $printer): int
    {
        $coverageFile = $this->getArgument('coverage-file');
        $patchFile = $this->getRequiredStringOption('patch');
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
            $printer->printLine('No executable lines found in patch.');
            $printer->printLine('');
            return 0;
        }

        $percentage = ($totalCoveredLines / $totalChangedLines) * 100;
        $percentageFormatted = number_format($percentage, 2);

        $printer->printLine('Patch Coverage Statistics:');
        $printer->printLine('');
        $printer->printLine("  Changed executable lines: <white>{$totalChangedLines}</white>");
        $printer->printLine("  Covered lines:            <green>{$totalCoveredLines}</green>");
        $printer->printLine('  Uncovered lines:          <orange>' . ($totalChangedLines - $totalCoveredLines) . '</orange>');
        $printer->printLine("  Coverage:                 <white>{$percentageFormatted}%</white>");
        $printer->printLine('');

        return 0;
    }

}
