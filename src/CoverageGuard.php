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
use function array_combine;
use function array_fill_keys;
use function array_keys;
use function file_get_contents;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

final class CoverageGuard
{

    private Parser $phpParser;

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->phpParser = (new ParserFactory())->createForHostVersion();
    }

    /**
     * @return list<CodeBlock>
     */
    public function checkCoverage(
        string $coverageFile,
        ?string $patchFile = null,
    ): array
    {
        $coveragePerFile = $this->getCoverage($coverageFile);
        $changesPerFile = $patchFile === null
            ? array_fill_keys(array_keys($coveragePerFile), null)
            : $this->getPatchChangedLines($patchFile);

        $untestedBlocks = [];

        foreach ($changesPerFile as $file => $changedLines) {
            if (!isset($coveragePerFile[$file])) {
                throw new LogicException("Coverage data for file {$file} not found");
            }
            $fileCoverage = $coveragePerFile[$file];

            foreach ($this->getUntestedChangedBlocks($file, $changedLines, $fileCoverage) as $untestedBlock) {
                $untestedBlocks[] = $untestedBlock;
            }
        }

        return $untestedBlocks;
    }

    /**
     * @param list<int>|null $linesChanged
     * @param array<int, int> $linesCoverage executable_line => hits
     * @return list<CodeBlock>
     */
    private function getUntestedChangedBlocks(
        string $file,
        ?array $linesChanged,
        array $linesCoverage,
    ): array
    {
        $nameResolver = new NameResolver();
        $linesChangedMap = $linesChanged === null ? null : array_combine($linesChanged, $linesChanged);
        $extractor = new ExtractUntestedChangedBlocksVisitor($file, $linesChangedMap, $linesCoverage);
        $traverser = new NodeTraverser($nameResolver, $extractor);

        $code = file_get_contents($file);

        if ($code === false) {
            throw new LogicException("Failed to read file: {$file}");
        }

        $ast = $this->phpParser->parse($code);

        if ($ast === null) {
            throw new LogicException("Failed to parse PHP code in file {$file}");
        }

        $traverser->traverse($ast);

        return $extractor->getUntestedBlocks();
    }

    /**
     * @return array<string, list<int>>
     */
    private function getPatchChangedLines(string $patchFile): array
    {
        if (!str_ends_with($patchFile, '.patch')) {
            throw new LogicException("Unknown patch file format: {$patchFile}");
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
