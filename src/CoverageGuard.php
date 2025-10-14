<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use Composer\InstalledVersions;
use LogicException;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser as DiffParser;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoberturaCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\PhpUnitCoverageExtractor;
use ShipMonk\CoverageGuard\Report\CoverageReport;
use ShipMonk\CoverageGuard\Report\ReportedError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\DefaultCoverageRule;
use function array_combine;
use function array_fill_keys;
use function array_keys;
use function array_map;
use function count;
use function file;
use function file_get_contents;
use function implode;
use function is_file;
use function method_exists;
use function range;
use function realpath;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use const FILE_IGNORE_NEW_LINES;

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

    /**
     * @throws ErrorException
     */
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
     * @return list<ReportedError>
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

        $ast = $this->phpParser->parse(implode('', $codeLines));

        if ($ast === null) {
            throw new LogicException("Failed to parse PHP code in file {$file}");
        }

        $traverser->traverse($ast);

        return $extractor->getReportedErrors();
    }

    /**
     * @return array<string, list<int>>
     *
     * @throws ErrorException
     */
    private function getPatchChangedLines(string $patchFile): array
    {
        if (!InstalledVersions::isInstalled('sebastian/diff')) {
            throw new ErrorException('In order to use --patch mode, you need to install sebastian/diff');
        }

        $gitRoot = $this->config->getGitRoot();
        $patchContent = file_get_contents($patchFile);

        if ($gitRoot === null) {
            throw new ErrorException('In order to process patch files, you need to be inside git repository folder, install git or use $config->setGitRoot(..).');
        }

        if ($patchContent === false) {
            throw new ErrorException("Failed to read patch file: {$patchFile}");
        }

        $patch = (new DiffParser())->parse($patchContent);
        $changes = [];

        foreach ($patch as $diff) {
            $diffTo = method_exists($diff, 'to') ? $diff->to() : $diff->getTo();
            if ($diffTo === '/dev/null') {
                continue; // deleted file
            }
            if (!str_starts_with($diffTo, 'b/')) {
                throw new ErrorException("Patch file '{$patchFile}' uses unsupported prefix in '{$diffTo}'. Only standard 'b/' is supported. Please use 'git diff --dst-prefix=b/' to regenerate the patch file.");
            }
            $absolutePath = $gitRoot . substr($diffTo, 2);

            if (!is_file($absolutePath)) {
                throw new ErrorException("File '{$absolutePath}' present in patch file '{$patchFile}' was not found. Is the patch up-to-date?");
            }

            $realPath = $this->realpath($absolutePath);
            $actualFileLines = $this->readFileLines($realPath, withLineEnding: false);

            $changes[$realPath] = [];

            $diffChunks = method_exists($diff, 'chunks') ? $diff->chunks() : $diff->getChunks();
            foreach ($diffChunks as $chunk) {
                $lineNumber = method_exists($chunk, 'end') ? $chunk->end() : $chunk->getEnd();
                $chunkLines = method_exists($chunk, 'lines') ? $chunk->lines() : $chunk->getLines();

                foreach ($chunkLines as $line) {
                    $lineType = method_exists($line, 'type') ? $line->type() : $line->getType();
                    $lineContent = method_exists($line, 'content') ? $line->content() : $line->getContent();

                    if ($lineType === Line::ADDED) {
                        if (!isset($actualFileLines[$lineNumber - 1])) {
                            throw new ErrorException("Patch file '{$patchFile}' refers to added line #{$lineNumber} of file '{$realPath}', but such line does not exist. Is the patch up-to-date?");
                        }

                        $actualLine = $actualFileLines[$lineNumber - 1];

                        if ($lineContent !== $actualLine) {
                            throw new ErrorException("Patch file '{$patchFile}' has added line #{$lineNumber} that does not match actual content of file '{$realPath}'.\nExpected '{$lineContent}'\nFound '{$actualLine}'\n\nIs the patch up-to-date?");
                        }
                    }

                    if ($lineType === Line::ADDED) {
                        $changes[$realPath][] = $lineNumber;
                    }

                    if ($lineType !== Line::REMOVED) {
                        $lineNumber++;
                    }
                }
            }
        }

        return $changes;
    }

    /**
     * @return array<string, FileCoverage>
     *
     * @throws ErrorException
     */
    private function getCoverage(string $coverageFile): array
    {
        $coverages = $this->createExtractor($coverageFile)->getCoverage($coverageFile);
        $remappedCoverages = [];

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
            }

            $remappedCoverages[$realPath] = new FileCoverage($realPath, $fileCoverage->executableLines, $fileCoverage->expectedLinesCount);
        }

        return $remappedCoverages;
    }

    /**
     * @throws ErrorException
     */
    private function createExtractor(string $coverageFile): CoverageExtractor
    {
        if (str_ends_with($coverageFile, '.cov')) {
            return new PhpUnitCoverageExtractor();
        }

        if (str_ends_with($coverageFile, '.xml')) {
            return $this->detectExtractorForXml($coverageFile);
        }

        throw new ErrorException("Unknown coverage file format: '{$coverageFile}'. Expecting .cov or .xml");
    }

    /**
     * @throws ErrorException
     */
    private function detectExtractorForXml(
        string $xmlFile,
    ): CoverageExtractor
    {
        $xmlLoader = new XmlLoader();
        $content = file_get_contents($xmlFile);

        if ($content === false) {
            throw new ErrorException("Failed to read file: {$xmlFile}");
        }

        if (str_contains($content, 'cobertura')) {
            return new CoberturaCoverageExtractor($xmlLoader);
        }

        return new CloverCoverageExtractor($xmlLoader);
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
     * @return list<string> lines with original line endings
     */
    private function readFileLines(
        string $file,
        bool $withLineEnding = true,
    ): array
    {
        $lines = file($file, $withLineEnding ? 0 : FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new LogicException("Failed to read file: {$file}");
        }
        $lines[] = '';

        return $lines;
    }

}
