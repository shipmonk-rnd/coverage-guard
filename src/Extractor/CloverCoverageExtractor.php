<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use LogicException;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\XmlLoader;

final class CloverCoverageExtractor implements CoverageExtractor
{

    public function __construct(
        private readonly XmlLoader $xmlLoader,
    )
    {
    }

    public function getCoverage(string $coverageFile): array
    {
        $xml = $this->xmlLoader->readXml($coverageFile);

        $coverage = [];
        $fileNodes = $xml->xpath('//file');

        if ($fileNodes === null) {
            throw new LogicException('Invalid usage of XPath, cannot happen');
        }

        foreach ($fileNodes as $fileNode) {
            $filePath = (string) $fileNode['name'];
            $linesOfCode = isset($fileNode->metrics) ? (int) $fileNode->metrics['loc'] : null;

            if (!isset($fileNode->line)) {
                continue;
            }

            $executableLines = [];

            foreach ($fileNode->line as $lineNode) {
                $lineNumber = (int) $lineNode['num'];
                $lineType = (string) $lineNode['type'];
                $hitCount = (int) $lineNode['count'];
                if ($lineType !== 'stmt') {
                    continue;
                }
                $executableLines[] = new ExecutableLine($lineNumber, $hitCount);
            }

            $coverage[] = new FileCoverage($filePath, $executableLines, $linesOfCode);
        }

        return $coverage;
    }

}
