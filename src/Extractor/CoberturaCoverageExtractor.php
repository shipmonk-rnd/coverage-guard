<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use ShipMonk\CoverageGuard\XmlLoader;
use SimpleXMLElement;
use function str_starts_with;
use function strlen;
use function substr;
use const DIRECTORY_SEPARATOR;

final class CoberturaCoverageExtractor implements CoverageExtractor
{

    /**
     * @param list<string> $stripPaths
     */
    public function __construct(
        private XmlLoader $xmlLoader,
        private array $stripPaths = [],
    )
    {
    }

    /**
     * @return array<string, array<int, int>> file_path => [executable_line => hits]
     */
    public function getCoverage(string $coverageFile): array
    {
        $xml = $this->xmlLoader->readXml($coverageFile);

        $coverage = [];
        $source = $this->extractSource($xml);
        $classNodes = $xml->xpath('//class');

        if ($classNodes === null) {
            return $coverage;
        }

        foreach ($classNodes as $classNode) {
            $filename = (string) $classNode['filename'];

            // Combine source path with filename to get full path
            $filePath = $this->resolveFilePath($source, $filename);
            $normalizedPath = $this->normalizePath($filePath);

            if (!isset($coverage[$normalizedPath])) {
                $coverage[$normalizedPath] = [];
            }

            if (!isset($classNode->lines->line)) {
                continue;
            }

            foreach ($classNode->lines->line as $lineNode) {
                $lineNumber = (int) $lineNode['number'];
                $hitCount = (int) $lineNode['hits'];
                $coverage[$normalizedPath][$lineNumber] = $hitCount;
            }
        }

        return $coverage;
    }

    private function extractSource(SimpleXMLElement $xml): ?string
    {
        $sourceNodes = $xml->xpath('//sources/source');

        if ($sourceNodes !== null) {
            foreach ($sourceNodes as $sourceNode) {
                return (string) $sourceNode;
            }
        }

        return null;
    }

    private function resolveFilePath(
        ?string $source,
        string $filename,
    ): string
    {
        if (str_starts_with($filename, '/')) {
            return $filename;
        }

        if ($source !== null) {
            return $source . DIRECTORY_SEPARATOR . $filename;
        }

        return $filename;
    }

    private function normalizePath(string $filePath): string
    {
        foreach ($this->stripPaths as $stripPath) {
            if (str_starts_with($filePath, $stripPath)) {
                return substr($filePath, strlen($stripPath));
            }
        }

        return $filePath;
    }

}
