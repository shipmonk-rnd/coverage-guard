<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use DOMDocument;
use DOMElement;
use DOMException;
use LogicException;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Utils\Indenter;
use function extension_loaded;
use function time;

final class CloverCoverageWriter implements CoverageWriter
{

    /**
     * @param array<FileCoverage> $fileCoverages
     *
     * @throws ErrorException
     */
    public function write(
        array $fileCoverages,
        string $indent,
    ): string
    {
        try {
            $dom = $this->generateXml($fileCoverages);
        } catch (DOMException $e) {
            throw new LogicException('Failed to generate cobertura XML: ' . $e->getMessage(), 0, $e);
        }
        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new ErrorException('Failed to generate clover XML');
        }

        return Indenter::change($xml, '  ', $indent);
    }

    /**
     * @param array<FileCoverage> $fileCoverages
     *
     * @throws ErrorException
     * @throws DOMException
     */
    private function generateXml(array $fileCoverages): DOMDocument
    {
        if (!extension_loaded('dom')) {
            throw new ErrorException('In order to output clover files, you need to enable the dom extension');
        }

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

    /**
     * @throws DOMException
     */
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
