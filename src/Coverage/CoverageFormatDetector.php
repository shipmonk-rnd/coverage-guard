<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Coverage;

use ShipMonk\CoverageGuard\Cli\CoverageInputFormat;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function file_get_contents;
use function is_file;
use function str_contains;
use function str_ends_with;

final class CoverageFormatDetector
{

    /**
     * @throws ErrorException
     */
    public function detectFormat(string $coverageFile): CoverageInputFormat
    {
        if (!is_file($coverageFile)) {
            throw new ErrorException("Coverage file not found: {$coverageFile}");
        }

        if (str_ends_with($coverageFile, '.cov')) {
            return CoverageInputFormat::Php;
        }

        if (str_ends_with($coverageFile, '.xml')) {
            return $this->detectFormatForXml($coverageFile);
        }

        throw new ErrorException("Unknown coverage file format: '{$coverageFile}'. Expecting .cov or .xml");
    }

    /**
     * @throws ErrorException
     */
    private function detectFormatForXml(string $xmlFile): CoverageInputFormat
    {
        $content = file_get_contents($xmlFile);

        if ($content === false) {
            throw new ErrorException("Failed to read file: {$xmlFile}");
        }

        if (str_contains($content, '<project')) {
            return CoverageInputFormat::Clover;
        }

        return CoverageInputFormat::Cobertura;
    }

}
