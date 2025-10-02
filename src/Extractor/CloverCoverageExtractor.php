<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use ShipMonk\CoverageGuard\XmlLoader;
use function str_starts_with;
use function strlen;
use function substr;

final class CloverCoverageExtractor implements CoverageExtractor
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
