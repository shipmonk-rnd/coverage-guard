<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use LogicException;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\XmlLoader;
use SimpleXMLElement;
use function ksort;
use function max;
use function str_starts_with;

final class CoberturaCoverageExtractor implements CoverageExtractor
{

    public function __construct(
        private readonly XmlLoader $xmlLoader,
    )
    {
    }

    public function getCoverage(string $coverageFile): array
    {
        $xml = $this->xmlLoader->readXml($coverageFile);

        $source = $this->extractSource($xml, $coverageFile);
        $classNodes = $xml->xpath('//class');

        if ($classNodes === null) {
            throw new LogicException('Invalid usage of XPath, cannot happen');
        }

        /** @var array<string, array<int, int>> $fileLineHits file path => [line number => hits] */
        $fileLineHits = [];

        // PHPUnit writes one <class> per class (plus one for functions), each with only its own lines
        foreach ($classNodes as $classNode) {
            $filename = (string) $classNode['filename'];

            // Combine source path with filename to get full path
            $filePath = $this->resolveFilePath($source, $filename);

            if (!isset($classNode->lines->line)) {
                continue;
            }

            foreach ($classNode->lines->line as $lineNode) {
                $lineNumber = (int) $lineNode['number'];
                $hitCount = (int) $lineNode['hits'];

                // a line listed twice in one report is the same line, its hits must not add up
                $fileLineHits[$filePath][$lineNumber] = max($fileLineHits[$filePath][$lineNumber] ?? 0, $hitCount);
            }
        }

        $coverage = [];

        foreach ($fileLineHits as $filePath => $lineHits) {
            ksort($lineHits);

            $executableLines = [];

            foreach ($lineHits as $lineNumber => $hitCount) {
                $executableLines[] = new ExecutableLine($lineNumber, $hitCount);
            }

            $coverage[] = new FileCoverage($filePath, $executableLines);
        }

        return $coverage;
    }

    /**
     * @throws ErrorException
     */
    private function extractSource(
        SimpleXMLElement $xml,
        string $coverageFile,
    ): string
    {
        $sourceNodes = $xml->xpath('//sources/source');

        if ($sourceNodes !== null) {
            foreach ($sourceNodes as $sourceNode) {
                return (string) $sourceNode;
            }
        }

        throw new ErrorException("Unable to find 'source' node in cobertura XML file '$coverageFile'");
    }

    private function resolveFilePath(
        string $source,
        string $filename,
    ): string
    {
        if (str_starts_with($filename, '/')) {
            return $filename;
        }

        return $source . '/' . $filename;
    }

}
