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
use ShipMonk\CoverageGuard\Extractor\ExtractorFactory;
use ShipMonk\CoverageGuard\Report\CoverageReport;
use ShipMonk\CoverageGuard\Report\ReportedError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\EnforceCoverageForMethodsRule;
use ShipMonk\CoverageGuard\Utils\PatchParser;
use function array_combine;
use function array_fill_keys;
use function array_keys;
use function array_map;
use function count;
use function file;
use function implode;
use function is_file;
use function range;
use function realpath;
use function rtrim;
use function str_starts_with;
use function strlen;
use function substr;
use const PHP_EOL;

final class CoverageGuard
{

    public function __construct(
        private readonly Printer $printer,
        private readonly PhpParser $phpParser,
        private readonly Config $config,
        private readonly PathHelper $pathHelper,
        private readonly PatchParser $patchParser,
        private readonly ExtractorFactory $extractorFactory,
    )
    {
    }

    /**
     * @throws ErrorException
     */
    public function checkCoverage(
        string $coverageFile,
        ?string $patchFile,
        bool $verbose,
    ): CoverageReport
    {
        $patchMode = $patchFile !== null;
        $coveragePerFile = $this->getCoverage($coverageFile);
        $changesPerFile = $patchFile === null
            ? array_fill_keys(array_keys($coveragePerFile), null)
            : $this->patchParser->getPatchChangedLines($patchFile, $this->config);

        $rules = $this->config->getRules();
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
        $codeLines = $this->readFileLines($file);
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

    /**
     * @return array<string, FileCoverage>
     *
     * @throws ErrorException
     */
    private function getCoverage(string $coverageFile): array
    {
        $coverages = $this->extractorFactory->createExtractor($coverageFile)->getCoverage($coverageFile);
        $foundHit = false;
        $remappedCoverages = [];

        if ($coverages === []) {
            throw new ErrorException("Coverage file '{$coverageFile}' does not contain any coverage data. Is it valid PHPUnit coverage file?");
        }

        foreach ($coverages as $fileCoverage) {
            $filePath = $fileCoverage->filePath;
            $newFilePath = $this->mapCoverageFilePath($filePath);
            $pathMappingInfo = $newFilePath === $filePath ? '' : " (mapped from '{$filePath}')";

            if (!is_file($newFilePath)) {
                throw new ErrorException("File '$newFilePath'$pathMappingInfo referenced in coverage file '$coverageFile' was not found. Is the report up-to-date?");
            }

            $realPath = $this->realpath($newFilePath);
            $codeLines = $this->readFileLines($realPath);
            $codeLinesCount = count($codeLines);

            // integrity checks follow
            if ($fileCoverage->expectedLinesCount !== null && $fileCoverage->expectedLinesCount !== $codeLinesCount) {
                throw new ErrorException("Coverage file '{$coverageFile}' refers to file '{$realPath}'{$pathMappingInfo} with {$fileCoverage->expectedLinesCount} lines of code, but the actual file has {$codeLinesCount} lines of code. Is the report up-to-date?");
            }

            foreach ($fileCoverage->executableLines as $executableLine) {
                $lineNumber = $executableLine->lineNumber;

                if ($lineNumber > $codeLinesCount) {
                    throw new ErrorException("Coverage file '{$coverageFile}' refers to line #{$lineNumber} of file '{$realPath}'{$pathMappingInfo}, but such line does not exist. Is the report up-to-date?");
                }

                if (!$foundHit && $executableLine->hits > 0) {
                    $foundHit = true;
                }
            }

            $remappedCoverages[$realPath] = new FileCoverage($realPath, $fileCoverage->executableLines, $fileCoverage->expectedLinesCount);
        }

        if (!$foundHit) {
            $this->printer->printWarning("Coverage file '{$coverageFile}' does not contain any executed line. Looks like not a single test was executed.");
        }

        return $remappedCoverages;
    }

    private function mapCoverageFilePath(string $filePath): string
    {
        foreach ($this->config->getCoveragePathMapping() as $oldPath => $newPath) {
            if (str_starts_with($filePath, $oldPath)) {
                return $newPath . substr($filePath, strlen($oldPath));
            }
        }

        return $filePath;
    }

    /**
     * @throws ErrorException
     */
    private function realpath(string $path): string
    {
        $realpath = realpath($path);
        if ($realpath === false) {
            throw new ErrorException("Could not realpath '$path'");
        }
        return $realpath;
    }

    /**
     * @return list<string>
     */
    private function readFileLines(string $file): array
    {
        $lines = file($file);
        if ($lines === false) {
            throw new LogicException("Failed to read file: {$file}");
        }

        if ($lines === []) {
            return [];
        }

        $lastLineIndex = count($lines) - 1;
        $lastLine = $lines[$lastLineIndex];

        if (rtrim($lastLine, "\n\r") !== $lastLine) {
            $lines[] = ''; // if last line ends with newline, add empty line to ensure expected number of lines is reached
        }

        return array_map(static fn (string $line): string => rtrim($line, "\n\r"), $lines);
    }

}
