<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use Composer\InstalledVersions;
use LogicException;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser as DiffParser;
use SimpleXMLElement;
use function array_combine;
use function array_fill_keys;
use function array_keys;
use function count;
use function extension_loaded;
use function file_get_contents;
use function get_debug_type;
use function is_array;
use function is_int;
use function libxml_clear_errors;
use function libxml_get_last_error;
use function libxml_use_internal_errors;
use function simplexml_load_file;
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
    private function readCoverageFromClover(string $cloverFilePath): array
    {
        $xml = $this->readXml($cloverFilePath);

        $coverage = [];
        $fileNodes = $xml->xpath('//file');

        if ($fileNodes === null) {
            return $coverage;
        }

        foreach ($fileNodes as $fileNode) {
            $filePath = $this->normalizePath((string) $fileNode['name']);
            $coverage[$filePath] = [];

            if (!isset($fileNode->line)) {
                continue;
            }

            foreach ($fileNode->line as $lineNode) {
                $lineNumber = (int) $lineNode['num'];
                $hitCount = (int) $lineNode['count'];
                $coverage[$filePath][$lineNumber] = $hitCount;
            }
        }

        return $coverage;
    }

    /**
     * @return array<string, array<int, int>> file_path => [executable_line => hits]
     */
    private function readCoverageFromCov(string $covFilePath): array
    {
        if (!InstalledVersions::isInstalled('phpunit/php-code-coverage')) {
            throw new LogicException('In order to use .cov coverage files, you need to install phpunit/php-code-coverage');
        }

        $coverage = (static function (string $file): mixed {
            return include $file;
        })($covFilePath);

        if (!$coverage instanceof CodeCoverage) {
            throw new LogicException("Invalid coverage file: '{$covFilePath}'. Expected serialized CodeCoverage instance, got " . get_debug_type($coverage));
        }

        $result = [];
        $lineCoverage = $coverage->getData()->lineCoverage();

        foreach ($lineCoverage as $filePath => $fileCoverage) {
            if (!is_array($fileCoverage)) {
                continue;
            }

            $normalizedPath = $this->normalizePath((string) $filePath);

            foreach ($fileCoverage as $lineNumber => $tests) {
                if (!is_int($lineNumber) || !is_array($tests)) {
                    continue;
                }

                $result[$normalizedPath][$lineNumber] = count($tests);
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<int, int>> file_path => [executable_line => hits]
     */
    private function getCoverage(string $coverageFile): array
    {
        if (str_ends_with($coverageFile, '.cov')) {
            return $this->readCoverageFromCov($coverageFile);
        }

        if (str_ends_with($coverageFile, '.xml')) {
            return $this->readCoverageFromClover($coverageFile);
        }

        throw new LogicException("Unknown coverage file format: '{$coverageFile}'. Expecting .cov or .xml");
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

    private function readXml(string $xmlFile): SimpleXMLElement
    {
        if (!extension_loaded('simplexml')) {
            throw new LogicException('In order to use xml coverage files, you need to enable the simplexml extension');
        }
        $libXmlErrorsOld = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlFile);

        if ($xml === false) {
            $libXmlError = libxml_get_last_error();
            $libXmlErrorMessage = $libXmlError === false ? '' : ' Error: ' . $libXmlError->message;
            throw new LogicException("Failed to parse clover XML file: {$xmlFile}." . $libXmlErrorMessage);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($libXmlErrorsOld);

        return $xml;
    }

}
