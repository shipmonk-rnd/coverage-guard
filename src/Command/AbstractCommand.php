<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoberturaCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\PhpUnitCoverageExtractor;
use ShipMonk\CoverageGuard\Writer\CloverCoverageWriter;
use ShipMonk\CoverageGuard\Writer\CoberturaCoverageWriter;
use ShipMonk\CoverageGuard\Writer\CoverageWriter;
use ShipMonk\CoverageGuard\XmlLoader;
use function file_get_contents;
use function is_file;
use function realpath;
use function str_contains;
use function str_ends_with;

abstract class AbstractCommand implements Command
{

    /**
     * Create coverage extractor based on file extension and content
     *
     * @throws ErrorException
     */
    protected function createExtractor(string $coverageFile): CoverageExtractor
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

    /**
     * Create coverage writer for the specified format
     */
    protected function createWriter(CoverageFormat $format): CoverageWriter
    {
        return match ($format) {
            CoverageFormat::Clover => new CloverCoverageWriter(),
            CoverageFormat::Cobertura => new CoberturaCoverageWriter(),
        };
    }

    /**
     * Get realpath or null if file doesn't exist
     */
    protected function tryRealpath(string $path): ?string
    {
        $realpath = realpath($path);
        return $realpath === false ? null : $realpath;
    }

}
