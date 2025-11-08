<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\XmlLoader;
use function file_get_contents;
use function is_file;
use function str_contains;
use function str_ends_with;

final class ExtractorFactory
{

    /**
     * @throws ErrorException
     */
    public function createExtractor(string $coverageFile): CoverageExtractor
    {
        if (!is_file($coverageFile)) {
            throw new ErrorException("Coverage file not found: {$coverageFile}");
        }

        if (str_ends_with($coverageFile, '.cov')) {
            return new PhpUnitCoverageExtractor();
        }

        if (str_ends_with($coverageFile, '.xml')) {
            return $this->detectExtractorForXml($coverageFile);
        }

        throw new ErrorException("Unknown coverage file format: '{$coverageFile}'. Expecting .cov or .xml");
    }

    /**
     * @throws ErrorException
     */
    private function detectExtractorForXml(string $xmlFile): CoverageExtractor
    {
        $xmlLoader = new XmlLoader();
        $content = file_get_contents($xmlFile);

        if ($content === false) {
            throw new ErrorException("Failed to read file: {$xmlFile}");
        }

        if (str_contains($content, 'cobertura')) {
            return new CoberturaCoverageExtractor($xmlLoader);
        }

        return new CloverCoverageExtractor($xmlLoader);
    }

}
