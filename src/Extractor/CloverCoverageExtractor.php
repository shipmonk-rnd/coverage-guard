<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use ShipMonk\CoverageGuard\XmlLoader;

final class CloverCoverageExtractor implements CoverageExtractor
{

    public function __construct(
        private XmlLoader $xmlLoader,
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
            $filePath = (string) $fileNode['name'];
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

}
