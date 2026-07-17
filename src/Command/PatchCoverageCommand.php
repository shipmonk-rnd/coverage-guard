<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Ast\FileTraverser;
use ShipMonk\CoverageGuard\Cli\Arguments\CoverageFileCliArgument;
use ShipMonk\CoverageGuard\Cli\Options\ConfigCliOption;
use ShipMonk\CoverageGuard\Cli\Options\PatchCliOption;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Excluder\ExcluderVisitor;
use ShipMonk\CoverageGuard\Excluder\ExclusionContext;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Utils\FileUtils;
use ShipMonk\CoverageGuard\Utils\PatchParser;
use function array_combine;
use function array_filter;
use function array_values;
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

            $fileCoverage = $coveragePerFile[$file];
            $executableLinesMap = [];

            foreach ($fileCoverage->executableLines as $line) {
                $executableLinesMap[$line->lineNumber] = $line->hits > 0;
            }

            $changedExecutableLines = array_values(array_filter(
                $changedLines,
                static fn (int $lineNumber): bool => isset($executableLinesMap[$lineNumber]),
            ));

            $excluderVisitor = null;
            if ($excluders !== [] && $changedExecutableLines !== []) {
                $fileLines = FileUtils::readFileLines($file);
                $linesContents = array_combine(range(1, count($fileLines)), $fileLines);
                $excluderVisitor = new ExcluderVisitor($excluders, new ExclusionContext($file, $linesContents));
                $this->fileTraverser->traverse($file, $fileLines, $excluderVisitor);
            }

            foreach ($changedExecutableLines as $lineNumber) {
                if ($excluderVisitor?->isLineExcluded($lineNumber) === true) {
                    continue;
                }
                $totalChangedLines++;
                if ($executableLinesMap[$lineNumber]) {
                    $totalCoveredLines++;
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
        $this->stdoutPrinter->printLine("  Changed executable lines: {$totalChangedLines}");
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
