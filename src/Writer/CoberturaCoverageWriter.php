<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use DOMDocument;
use DOMElement;
use DOMException;
use LogicException;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Utils\Indenter;
use function array_reduce;
use function basename;
use function count;
use function dirname;
use function explode;
use function extension_loaded;
use function implode;
use function min;
use function number_format;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function time;
use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

final class CoberturaCoverageWriter implements CoverageWriter
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
            throw new LogicException('Failed to generate cobertura XML');
        }

        return str_replace("\n", PHP_EOL, Indenter::change($xml, '  ', $indent));
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
            throw new ErrorException('In order to output cobertura files, you need to enable the dom extension');
        }

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
        $coverage->setAttribute('line-rate', number_format($globalLineRate, decimals: 5));
        $coverage->setAttribute('branch-rate', '0');
        $coverage->setAttribute('lines-covered', (string) $globalLinesCovered);
        $coverage->setAttribute('lines-valid', (string) $globalLinesValid);
        $coverage->setAttribute('branches-covered', '0');
        $coverage->setAttribute('branches-valid', '0');
        $coverage->setAttribute('complexity', '0');
        $coverage->setAttribute('version', '0.4');
        $coverage->setAttribute('timestamp', (string) $timestamp);
        $dom->appendChild($coverage);

        $source = $this->findCommonParentDirectory($fileCoverages);

        $this->addSource($dom, $coverage, $source);
        $this->addPackages($dom, $coverage, $source, $fileCoverages);

        return $dom;
    }

    /**
     * @throws DOMException
     */
    private function addSource(
        DOMDocument $dom,
        DOMElement $coverage,
        string $source,
    ): void
    {
        $sourcesElement = $dom->createElement('sources');
        $coverage->appendChild($sourcesElement);

        $sourceElement = $dom->createElement('source', $source);
        $sourcesElement->appendChild($sourceElement);
    }

    /**
     * Find the common parent directory for all file coverages
     *
     * @param array<FileCoverage> $fileCoverages
     */
    private function findCommonParentDirectory(array $fileCoverages): string
    {
        if (count($fileCoverages) === 0) {
            return '.';
        }

        $dirs = [];
        foreach ($fileCoverages as $fileCoverage) {
            $dirs[] = dirname($fileCoverage->filePath);
        }

        // If only one directory, return it
        if (count($dirs) === 1) {
            return $dirs[0];
        }

        $commonPath = $dirs[0];
        foreach ($dirs as $dir) {
            $commonPath = $this->findCommonPrefix($commonPath, $dir);
        }

        return $commonPath;
    }

    private function findCommonPrefix(
        string $path1,
        string $path2,
    ): string
    {
        $parts1 = explode(DIRECTORY_SEPARATOR, $path1);
        $parts2 = explode(DIRECTORY_SEPARATOR, $path2);

        $common = [];
        $minLength = min(count($parts1), count($parts2));

        for ($i = 0; $i < $minLength; $i++) {
            if (isset($parts1[$i], $parts2[$i]) && $parts1[$i] === $parts2[$i]) {
                $common[] = $parts1[$i];
            } else {
                break;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $common);
    }

    /**
     * @param array<FileCoverage> $fileCoverages
     *
     * @throws DOMException
     */
    private function addPackages(
        DOMDocument $dom,
        DOMElement $coverage,
        string $source,
        array $fileCoverages,
    ): void
    {
        $packagesElement = $dom->createElement('packages');
        $coverage->appendChild($packagesElement);

        foreach ($fileCoverages as $fileCoverage) {
            $relativeFilename = $this->getRelativePath($source, $fileCoverage->filePath);

            $linesValid = count($fileCoverage->executableLines);
            $linesCovered = array_reduce($fileCoverage->executableLines, static fn (int $carry, ExecutableLine $line): int => $line->hits > 0 ? $carry + 1 : $carry, 0);
            $lineRate = $linesValid > 0 ? $linesCovered / $linesValid : 0.0;

            $packageElement = $this->createPackageElement($dom, $lineRate, $relativeFilename);
            $packagesElement->appendChild($packageElement);

            $classesElement = $dom->createElement('classes');
            $packageElement->appendChild($classesElement);

            $classElement = $this->createClassElement($dom, $fileCoverage, $lineRate, $relativeFilename);
            $classesElement->appendChild($classElement);
        }
    }

    /**
     * @throws DOMException
     */
    private function createPackageElement(
        DOMDocument $dom,
        float $lineRate,
        string $packageName,
    ): DOMElement
    {
        $packageElement = $dom->createElement('package');
        $packageElement->setAttribute('name', $packageName);
        $packageElement->setAttribute('line-rate', number_format($lineRate, decimals: 3));
        $packageElement->setAttribute('branch-rate', '0');
        $packageElement->setAttribute('complexity', '0');

        return $packageElement;
    }

    /**
     * @throws DOMException
     */
    private function createClassElement(
        DOMDocument $dom,
        FileCoverage $fileCoverage,
        float $lineRate,
        string $relativeFilename,
    ): DOMElement
    {
        // Derive a class name from the file path
        $className = basename($fileCoverage->filePath, '.php');
        $className = str_replace(DIRECTORY_SEPARATOR, '\\', $className);

        $classElement = $dom->createElement('class');
        $classElement->setAttribute('name', $className);
        $classElement->setAttribute('filename', $relativeFilename);
        $classElement->setAttribute('line-rate', number_format($lineRate, decimals: 3));
        $classElement->setAttribute('branch-rate', '0');
        $classElement->setAttribute('complexity', '0');

        // Empty methods element (required by Cobertura DTD)
        $methodsElement = $dom->createElement('methods');
        $classElement->appendChild($methodsElement);

        // Add lines
        $this->addLines($dom, $classElement, $fileCoverage);

        return $classElement;
    }

    /**
     * Get relative path from source directory to file path
     */
    private function getRelativePath(
        string $sourceDir,
        string $filePath,
    ): string
    {
        $fileDir = dirname($filePath);
        $fileName = basename($filePath);

        // If file is directly in source dir
        if ($fileDir === $sourceDir) {
            return $fileName;
        }

        // Calculate relative path from source to file directory
        if (str_starts_with($fileDir, $sourceDir . DIRECTORY_SEPARATOR)) {
            $relativePath = substr($fileDir, strlen($sourceDir) + 1);
            return $relativePath . DIRECTORY_SEPARATOR . $fileName;
        }

        throw new LogicException("File path '$filePath' is not within source directory '$sourceDir', broken source detection?");
    }

    /**
     * @throws DOMException
     */
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
