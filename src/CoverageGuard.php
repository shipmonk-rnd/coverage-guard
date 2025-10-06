<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser as DiffParser;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoberturaCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\PhpUnitCoverageExtractor;
use ShipMonk\CoverageGuard\Report\CoverageReport;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\DefaultCoverageRule;
use ShipMonk\CoverageGuard\Rule\ReportedError;
use function array_combine;
use function array_fill_keys;
use function array_keys;
use function count;
use function file;
use function file_get_contents;
use function implode;
use function is_file;
use function range;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

final class CoverageGuard
{

    private Printer $printer;

    private Parser $phpParser;

    private Config $config;

    public function __construct(
        Config $config,
        Printer $printer,
    )
    {
        $this->printer = $printer;
        $this->config = $config;
        $this->phpParser = (new ParserFactory())->createForHostVersion();
    }

    public function checkCoverage(
        string $coverageFile,
        ?string $patchFile = null,
    ): CoverageReport
    {
        $patchMode = $patchFile !== null;
        $coveragePerFile = $this->getCoverage($coverageFile);
        $changesPerFile = $patchFile === null
            ? array_fill_keys(array_keys($coveragePerFile), null)
            : $this->getPatchChangedLines($patchFile);

        $rules = $this->config->getRules();
        if ($rules === []) {
            $this->printer->printWarning('No rules configured, will report only long fully untested ' . ($patchMode ? 'and fully changed' : '') . 'methods!');

            $rules[] = new DefaultCoverageRule();
        }

        $analysedFiles = [];
        $reportedErrors = [];

        foreach ($changesPerFile as $file => $changedLinesOrNull) {
            if (!isset($coveragePerFile[$file])) {
                continue;
            }

            if (!is_file($file)) {
                throw new LogicException("File '{$file}' present in coverage data in '$coverageFile' was not found. Is the report up-to-date?");
            }

            $analysedFiles[] = $file;
            $linesCoverage = $coveragePerFile[$file];

            foreach ($this->getReportedErrors($rules, $patchMode, $file, $changedLinesOrNull, $linesCoverage) as $reportedError) {
                $reportedErrors[] = $reportedError;
            }
        }

        return new CoverageReport($reportedErrors, $analysedFiles, $patchMode);
    }

    /**
     * @param list<CoverageRule> $rules
     * @param list<int>|null $linesChanged
     * @param array<int, int> $linesCoverage executable_line => hits
     * @return list<ReportedError>
     */
    private function getReportedErrors(
        array $rules,
        bool $patchMode,
        string $file,
        ?array $linesChanged,
        array $linesCoverage,
    ): array
    {
        $codeLines = file($file);
        if ($codeLines === false) {
            throw new LogicException("Failed to read file: {$file}");
        }

        $nameResolver = new NameResolver();
        $linesChangedMap = $linesChanged === null
            ? array_combine(range(1, count($codeLines)), range(1, count($codeLines)))
            : array_combine($linesChanged, $linesChanged);

        $extractor = new CodeBlockAnalyser($patchMode, $file, $linesChangedMap, $linesCoverage, $rules);
        $traverser = new NodeTraverser($nameResolver, $extractor);

        $ast = $this->phpParser->parse(implode('', $codeLines));

        if ($ast === null) {
            throw new LogicException("Failed to parse PHP code in file {$file}");
        }

        $traverser->traverse($ast);

        return $extractor->getReportedErrors();
    }

    /**
     * @return array<string, list<int>>
     */
    private function getPatchChangedLines(string $patchFile): array
    {
        if (!str_ends_with($patchFile, '.patch')) {
            throw new LogicException("Unknown patch file format: {$patchFile}, expecting .patch extension");
        }
        $gitRoot = $this->config->getGitRoot();
        $patchContent = file_get_contents($patchFile);

        if ($gitRoot === null) {
            throw new LogicException('In order to process patch files, you need to be inside git repository folder, install git or use $config->setGitRoot(..).');
        }

        if ($patchContent === false) {
            throw new LogicException("Failed to read patch file: {$patchFile}");
        }

        $patch = (new DiffParser())->parse($patchContent);
        $changes = [];

        foreach ($patch as $diff) {
            $file = $this->normalizePath($gitRoot . substr($diff->to(), 2));
            $changes[$file] = [];

            foreach ($diff->chunks() as $chunk) {
                $lineNumber = $chunk->end();

                foreach ($chunk->lines() as $line) {
                    if ($line->type() === Line::ADDED) {
                        $changes[$file][] = $lineNumber;
                    }

                    if ($line->type() !== Line::REMOVED) {
                        $lineNumber++;
                    }
                }
            }
        }

        return $changes;
    }

    /**
     * @return array<string, array<int, int>> file_path => [executable_line => hits]
     */
    private function getCoverage(string $coverageFile): array
    {
        return $this->createExtractor($coverageFile)->getCoverage($coverageFile);
    }

    private function createExtractor(string $coverageFile): CoverageExtractor
    {
        if (str_ends_with($coverageFile, '.cov')) {
            return new PhpUnitCoverageExtractor($this->config->getStripPaths());
        }

        if (str_ends_with($coverageFile, '.xml')) {
            return $this->detectExtractorForXml($coverageFile);
        }

        throw new LogicException("Unknown coverage file format: '{$coverageFile}'. Expecting .cov or .xml");
    }

    private function detectExtractorForXml(
        string $xmlFile,
    ): CoverageExtractor
    {
        $xmlLoader = new XmlLoader();
        $content = file_get_contents($xmlFile);

        if ($content === false) {
            throw new LogicException("Failed to read file: {$xmlFile}");
        }

        if (str_contains($content, 'cobertura')) {
            return new CoberturaCoverageExtractor($xmlLoader, $this->config->getStripPaths());
        }

        return new CloverCoverageExtractor($xmlLoader, $this->config->getStripPaths());
    }

    private function normalizePath(string $filePath): string
    {
        foreach ($this->config->getStripPaths() as $stripPath) {
            if (str_starts_with($filePath, $stripPath)) {
                return substr($filePath, strlen($stripPath));
            }
        }

        return $filePath;
    }

}
