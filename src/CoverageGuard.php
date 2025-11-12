<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PhpParser\Error as ParseError;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser as PhpParser;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Report\CoverageReport;
use ShipMonk\CoverageGuard\Report\ReportedError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\EnforceCoverageForMethodsRule;
use ShipMonk\CoverageGuard\Utils\FileUtils;
use ShipMonk\CoverageGuard\Utils\PatchParser;
use function array_combine;
use function array_fill_keys;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function range;
use const PHP_EOL;

final class CoverageGuard
{

    public function __construct(
        private readonly Printer $printer,
        private readonly PhpParser $phpParser,
        private readonly PathHelper $pathHelper,
        private readonly PatchParser $patchParser,
        private readonly CoverageProvider $extractorFactory,
    )
    {
    }

    /**
     * @throws ErrorException
     */
    public function checkCoverage(
        Config $config,
        string $coverageFile,
        ?string $patchFile,
        bool $verbose,
    ): CoverageReport
    {
        $patchMode = $patchFile !== null;
        $coveragePerFile = $this->extractorFactory->getCoverage($config, $coverageFile);
        $changesPerFile = $patchFile === null
            ? array_fill_keys(array_keys($coveragePerFile), null)
            : $this->patchParser->getPatchChangedLines($patchFile, $config);

        $rules = $config->getRules();
        if ($rules === []) {
            $this->printer->printWarning('No rules configured, will report only long fully untested methods!');

            $rules[] = new EnforceCoverageForMethodsRule(minExecutableLines: 5);
        }

        $analysedFiles = [];
        $reportedErrors = [];

        if ($verbose) {
            $where = $patchMode ? 'patch file' : 'coverage report';
            $this->printer->printInfo("Checking files listed in $where");
        }

        foreach ($changesPerFile as $file => $changedLinesOrNull) {
            if (!isset($coveragePerFile[$file])) {
                if ($patchMode && $verbose) {
                    $relativePath = $this->pathHelper->relativizePath($file);
                    $this->printer->printLine("<orange>{$relativePath}</orange> - skipped (not in coverage report)");
                }
                continue;
            }

            $analysedFiles[] = $file;
            $fileCoverage = $coveragePerFile[$file];

            if ($verbose) {
                $relativePath = $this->pathHelper->relativizePath($file);
                $coveragePerc = $fileCoverage->getCoveragePercentage();
                $this->printer->printLine("<white>{$relativePath}</white> - $coveragePerc%");
            }

            foreach ($this->getReportedErrors($rules, $patchMode, $file, $changedLinesOrNull, $fileCoverage) as $reportedError) {
                $reportedErrors[] = $reportedError;
            }
        }

        return new CoverageReport($reportedErrors, $analysedFiles, $patchMode);
    }

    /**
     * @param list<CoverageRule> $rules
     * @param list<int>|null $linesChanged
     * @return list<ReportedError>
     *
     * @throws ErrorException
     */
    private function getReportedErrors(
        array $rules,
        bool $patchMode,
        string $file,
        ?array $linesChanged,
        FileCoverage $fileCoverage,
    ): array
    {
        $codeLines = FileUtils::readFileLines($file);
        $lineNumbers = range(1, count($codeLines));

        $nameResolver = new NameResolver();
        $linesChangedMap = $linesChanged === null
            ? array_combine($lineNumbers, $lineNumbers)
            : array_combine($linesChanged, $linesChanged);

        $linesCoverage = array_combine(
            array_map(static fn (ExecutableLine $line) => $line->lineNumber, $fileCoverage->executableLines),
            array_map(static fn (ExecutableLine $line) => $line->hits, $fileCoverage->executableLines),
        );

        $linesContents = array_combine($lineNumbers, $codeLines);

        $extractor = new CodeBlockAnalyser($patchMode, $file, $linesChangedMap, $linesCoverage, $linesContents, $rules);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($extractor);

        try {
            /** @throws ParseError */
            $ast = $this->phpParser->parse(implode(PHP_EOL, $codeLines));
        } catch (ParseError $e) {
            throw new ErrorException("Failed to parse PHP code in file {$file}: {$e->getMessage()}", $e);
        }

        if ($ast === null) {
            throw new LogicException("Failed to parse PHP code in file {$file}. Should never happen as Throwing error handler is used.");
        }

        $traverser->traverse($ast);

        return $extractor->getReportedErrors();
    }

}
