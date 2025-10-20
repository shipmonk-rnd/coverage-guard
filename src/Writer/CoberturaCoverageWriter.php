<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use DOMDocument;
use DOMElement;
use RuntimeException;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use function basename;
use function count;
use function dirname;
use function fwrite;
use function number_format;
use function str_replace;
use function time;

final class CoberturaCoverageWriter implements CoverageWriter
{

    /**
     * @param list<FileCoverage> $fileCoverages
     * @param resource $output
     *
     * @throws RuntimeException
     */
    public function write(
        array $fileCoverages,
        $output,
    ): void
    {
        $dom = $this->generateXml($fileCoverages);
        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Failed to generate XML');
        }

        fwrite($output, $xml);
    }

    /**
     * @param list<FileCoverage> $fileCoverages
     */
    private function generateXml(array $fileCoverages): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Add DOCTYPE
        $doctype = $dom->implementation->createDocumentType(
            qualifiedName: 'coverage',
            publicId: '',
            systemId: 'http://cobertura.sourceforge.net/xml/coverage-04.dtd',
        );
        $dom->appendChild($doctype);

        $timestamp = time();

        // Calculate global stats
        $globalLinesValid = 0;
        $globalLinesCovered = 0;
        foreach ($fileCoverages as $fileCoverage) {
            $globalLinesValid += count($fileCoverage->executableLines);
            foreach ($fileCoverage->executableLines as $line) {
                if ($line->hits > 0) {
                    $globalLinesCovered++;
                }
            }
        }
        $globalLineRate = $globalLinesValid > 0 ? $globalLinesCovered / $globalLinesValid : 0.0;

        $coverage = $dom->createElement('coverage');
        $coverage->setAttribute('line-rate', number_format($globalLineRate, decimals: 14));
        $coverage->setAttribute('branch-rate', '0');
        $coverage->setAttribute('lines-covered', (string) $globalLinesCovered);
        $coverage->setAttribute('lines-valid', (string) $globalLinesValid);
        $coverage->setAttribute('branches-covered', '0');
        $coverage->setAttribute('branches-valid', '0');
        $coverage->setAttribute('complexity', '0');
        $coverage->setAttribute('version', '0.4');
        $coverage->setAttribute('timestamp', (string) $timestamp);
        $dom->appendChild($coverage);

        $this->addSources($dom, $coverage, $fileCoverages);
        $this->addPackages($dom, $coverage, $fileCoverages);

        return $dom;
    }

    /**
     * @param list<FileCoverage> $fileCoverages
     */
    private function addSources(
        DOMDocument $dom,
        DOMElement $coverage,
        array $fileCoverages,
    ): void
    {
        $sourcesElement = $dom->createElement('sources');
        $coverage->appendChild($sourcesElement);

        // Extract unique source directories
        $sources = [];
        foreach ($fileCoverages as $fileCoverage) {
            $dir = dirname($fileCoverage->filePath);
            if (!isset($sources[$dir])) {
                $sources[$dir] = true;
            }
        }

        foreach ($sources as $source => $unused) {
            $sourceElement = $dom->createElement('source', $source);
            $sourcesElement->appendChild($sourceElement);
        }
    }

    /**
     * @param list<FileCoverage> $fileCoverages
     */
    private function addPackages(
        DOMDocument $dom,
        DOMElement $coverage,
        array $fileCoverages,
    ): void
    {
        $packagesElement = $dom->createElement('packages');
        $coverage->appendChild($packagesElement);

        // Group files by package (basename)
        $packagedFiles = [];
        foreach ($fileCoverages as $fileCoverage) {
            $packageName = basename($fileCoverage->filePath);
            if (!isset($packagedFiles[$packageName])) {
                $packagedFiles[$packageName] = [];
            }
            $packagedFiles[$packageName][] = $fileCoverage;
        }

        foreach ($packagedFiles as $packageName => $files) {
            $packageElement = $this->createPackageElement($dom, $packageName, $files);
            $packagesElement->appendChild($packageElement);

            $classesElement = $dom->createElement('classes');
            $packageElement->appendChild($classesElement);

            foreach ($files as $fileCoverage) {
                $classElement = $this->createClassElement($dom, $fileCoverage);
                $classesElement->appendChild($classElement);
            }
        }
    }

    /**
     * @param list<FileCoverage> $files
     */
    private function createPackageElement(
        DOMDocument $dom,
        string $packageName,
        array $files,
    ): DOMElement
    {
        $packageLinesValid = 0;
        $packageLinesCovered = 0;

        foreach ($files as $fileCoverage) {
            $packageLinesValid += count($fileCoverage->executableLines);
            foreach ($fileCoverage->executableLines as $line) {
                if ($line->hits > 0) {
                    $packageLinesCovered++;
                }
            }
        }

        $packageLineRate = $packageLinesValid > 0 ? $packageLinesCovered / $packageLinesValid : 0.0;

        $packageElement = $dom->createElement('package');
        $packageElement->setAttribute('name', $packageName);
        $packageElement->setAttribute('line-rate', number_format($packageLineRate, decimals: 14));
        $packageElement->setAttribute('branch-rate', '0');
        $packageElement->setAttribute('complexity', '0');

        return $packageElement;
    }

    private function createClassElement(
        DOMDocument $dom,
        FileCoverage $fileCoverage,
    ): DOMElement
    {
        $classLinesValid = count($fileCoverage->executableLines);
        $classLinesCovered = 0;
        foreach ($fileCoverage->executableLines as $line) {
            if ($line->hits > 0) {
                $classLinesCovered++;
            }
        }

        $classLineRate = $classLinesValid > 0 ? $classLinesCovered / $classLinesValid : 0.0;

        // Derive a class name from the file path
        $className = basename($fileCoverage->filePath, '.php');
        $className = str_replace('/', '\\', $className);

        $classElement = $dom->createElement('class');
        $classElement->setAttribute('name', $className);
        $classElement->setAttribute('filename', basename($fileCoverage->filePath));
        $classElement->setAttribute('line-rate', number_format($classLineRate, decimals: 14));
        $classElement->setAttribute('branch-rate', '0');
        $classElement->setAttribute('complexity', '0');

        // Empty methods element (required by Cobertura DTD)
        $methodsElement = $dom->createElement('methods');
        $classElement->appendChild($methodsElement);

        // Add lines
        $this->addLines($dom, $classElement, $fileCoverage);

        return $classElement;
    }

    private function addLines(
        DOMDocument $dom,
        DOMElement $classElement,
        FileCoverage $fileCoverage,
    ): void
    {
        $linesElement = $dom->createElement('lines');
        $classElement->appendChild($linesElement);

        foreach ($fileCoverage->executableLines as $executableLine) {
            $lineElement = $dom->createElement('line');
            $lineElement->setAttribute('number', (string) $executableLine->lineNumber);
            $lineElement->setAttribute('hits', (string) $executableLine->hits);
            $linesElement->appendChild($lineElement);
        }
    }

}
