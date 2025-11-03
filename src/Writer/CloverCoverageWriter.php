<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use DOMDocument;
use DOMElement;
use RuntimeException;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Utils\Indenter;
use function time;

final class CloverCoverageWriter implements CoverageWriter
{

    /**
     * @param list<FileCoverage> $fileCoverages
     *
     * @throws RuntimeException
     */
    public function write(
        array $fileCoverages,
        string $indent,
    ): string
    {
        $dom = $this->generateXml($fileCoverages);
        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Failed to generate clover XML');
        }

        return Indenter::change($xml, '  ', $indent);
    }

    /**
     * @param list<FileCoverage> $fileCoverages
     */
    private function generateXml(array $fileCoverages): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $timestamp = time();

        $coverage = $dom->createElement('coverage');
        $coverage->setAttribute('generated', (string) $timestamp);
        $dom->appendChild($coverage);

        $project = $dom->createElement('project');
        $project->setAttribute('timestamp', (string) $timestamp);
        $coverage->appendChild($project);

        foreach ($fileCoverages as $fileCoverage) {
            $this->addFileElement($dom, $project, $fileCoverage);
        }

        return $dom;
    }

    private function addFileElement(
        DOMDocument $dom,
        DOMElement $project,
        FileCoverage $fileCoverage,
    ): void
    {
        $fileElement = $dom->createElement('file');
        $fileElement->setAttribute('name', $fileCoverage->filePath);
        $project->appendChild($fileElement);

        if ($fileCoverage->expectedLinesCount !== null) {
            $metricsElement = $dom->createElement('metrics');
            $metricsElement->setAttribute('loc', (string) $fileCoverage->expectedLinesCount);
            $fileElement->appendChild($metricsElement);
        }

        foreach ($fileCoverage->executableLines as $executableLine) {
            $lineElement = $dom->createElement('line');
            $lineElement->setAttribute('num', (string) $executableLine->lineNumber);
            $lineElement->setAttribute('type', 'stmt');
            $lineElement->setAttribute('count', (string) $executableLine->hits);
            $fileElement->appendChild($lineElement);
        }
    }

}
