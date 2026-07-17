<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Ast\FileTraverser;
use ShipMonk\CoverageGuard\Cli\Arguments\CoverageFileCliArgument;
use ShipMonk\CoverageGuard\Cli\Options\ConfigCliOption;
use ShipMonk\CoverageGuard\Cli\Options\PatchCliOption;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Excluder\ExcluderVisitor;
use ShipMonk\CoverageGuard\Excluder\ExclusionContext;
use ShipMonk\CoverageGuard\Excluder\ExecutableLineExcluder;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Utils\FileUtils;
use ShipMonk\CoverageGuard\Utils\PatchParser;
use function array_combine;
use function count;
use function number_format;
use function range;

final class PatchCoverageCommand implements Command
{

    public function __construct(
        private readonly Printer $stdoutPrinter,
        private readonly PatchParser $patchParser,
        private readonly ConfigResolver $configResolver,
        private readonly CoverageProvider $coverageProvider,
        private readonly FileTraverser $fileTraverser,
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
        $excluders = $config->getExecutableLineExcluders();

        // Calculate coverage for changed lines
        $totalChangedLines = 0;
        $totalCoveredLines = 0;

        foreach ($changesPerFile as $file => $changedLines) {
            if (!isset($coveragePerFile[$file])) {
                continue; // File not in coverage report
            }

            $changedExecutableLines = $this->getChangedExecutableLines($coveragePerFile[$file], $changedLines);

            $excluderVisitor = $excluders !== [] && $changedExecutableLines !== []
                ? $this->createExcluderVisitor($excluders, $file)
                : null;

            foreach ($changedExecutableLines as $lineNumber => $isCovered) {
                if ($excluderVisitor?->isLineExcluded($lineNumber) === true) {
                    continue;
                }
                $totalChangedLines++;
                if ($isCovered) {
                    $totalCoveredLines++;
                }
            }
        }

        $this->printStatistics($totalChangedLines, $totalCoveredLines);

        return 0;
    }

    /**
     * @param list<int> $changedLines
     * @return array<int, bool> line number => is covered
     */
    private function getChangedExecutableLines(
        FileCoverage $fileCoverage,
        array $changedLines,
    ): array
    {
        $executableLinesMap = [];
        foreach ($fileCoverage->executableLines as $line) {
            $executableLinesMap[$line->lineNumber] = $line->hits > 0;
        }

        $changedExecutableLines = [];
        foreach ($changedLines as $lineNumber) {
            if (isset($executableLinesMap[$lineNumber])) {
                $changedExecutableLines[$lineNumber] = $executableLinesMap[$lineNumber];
            }
        }

        return $changedExecutableLines;
    }

    /**
     * @param non-empty-list<ExecutableLineExcluder> $excluders
     *
     * @throws ErrorException
     */
    private function createExcluderVisitor(
        array $excluders,
        string $file,
    ): ExcluderVisitor
    {
        $fileLines = FileUtils::readFileLines($file);
        $linesContents = array_combine(range(1, count($fileLines)), $fileLines);

        $excluderVisitor = new ExcluderVisitor($excluders, new ExclusionContext($file, $linesContents));
        $this->fileTraverser->traverse($file, $fileLines, $excluderVisitor);

        return $excluderVisitor;
    }

    private function printStatistics(
        int $totalChangedLines,
        int $totalCoveredLines,
    ): void
    {
        if ($totalChangedLines === 0) {
            $percentage = 0;
        } else {
            $percentage = ($totalCoveredLines / $totalChangedLines) * 100;
        }

        $percentageFormatted = number_format($percentage, 2);

        $this->stdoutPrinter->printLine('Patch Coverage Statistics:');
        $this->stdoutPrinter->printLine('');
        $this->stdoutPrinter->printLine("  Changed executable lines: {$totalChangedLines}");
        $this->stdoutPrinter->printLine("  Covered lines:            <green>{$totalCoveredLines}</green>");
        $this->stdoutPrinter->printLine('  Uncovered lines:          <orange>' . ($totalChangedLines - $totalCoveredLines) . '</orange>');
        $this->stdoutPrinter->printLine("  Coverage:                 {$percentageFormatted}%");
        $this->stdoutPrinter->printLine('');
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
