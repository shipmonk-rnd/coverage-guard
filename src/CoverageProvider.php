<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use ShipMonk\CoverageGuard\Cli\CoverageInputFormat;
use ShipMonk\CoverageGuard\Coverage\CoverageFormatDetector;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoberturaCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\PhpUnitCoverageExtractor;
use ShipMonk\CoverageGuard\Utils\FileUtils;
use function count;
use function is_file;
use function str_starts_with;
use function strlen;
use function substr;

final class CoverageProvider
{

    public function __construct(
        private readonly CoverageFormatDetector $coverageFormatDetector,
        private readonly Printer $printer,
    )
    {
    }

    /**
     * @return array<string, FileCoverage>
     *
     * @throws ErrorException
     */
    public function getCoverage(
        Config $config,
        string $coverageFile,
    ): array
    {
        $coverages = $this->createExtractor($coverageFile)->getCoverage($coverageFile);
        $foundHit = false;
        $remappedCoverages = [];

        if ($coverages === []) {
            $this->printer->printWarning("Coverage file '{$coverageFile}' does not contain any coverage data.");
            return [];
        }

        foreach ($coverages as $fileCoverage) {
            $filePath = $fileCoverage->filePath;
            $newFilePath = $this->mapCoverageFilePath($config, $filePath);
            $pathMappingInfo = $newFilePath === $filePath ? '' : " (mapped from '{$filePath}')";

            if (!is_file($newFilePath)) {
                throw new ErrorException("File '$newFilePath'$pathMappingInfo referenced in coverage file '$coverageFile' was not found. Is the report up-to-date?");
            }

            $realPath = FileUtils::realpath($newFilePath);
            $codeLines = FileUtils::readFileLines($realPath);
            $codeLinesCount = count($codeLines);

            // integrity checks follow
            if ($fileCoverage->expectedLinesCount !== null && $fileCoverage->expectedLinesCount !== $codeLinesCount) {
                throw new ErrorException("Coverage file '{$coverageFile}' refers to file '{$realPath}'{$pathMappingInfo} with {$fileCoverage->expectedLinesCount} lines of code, but the actual file has {$codeLinesCount} lines of code. Is the report up-to-date?");
            }

            foreach ($fileCoverage->executableLines as $executableLine) {
                $lineNumber = $executableLine->lineNumber;

                if ($lineNumber > $codeLinesCount) {
                    throw new ErrorException("Coverage file '{$coverageFile}' refers to line #{$lineNumber} of file '{$realPath}'{$pathMappingInfo}, but such line does not exist. Is the report up-to-date?");
                }

                if (!$foundHit && $executableLine->hits > 0) {
                    $foundHit = true;
                }
            }

            $remappedCoverages[$realPath] = new FileCoverage($realPath, $fileCoverage->executableLines, $fileCoverage->expectedLinesCount);
        }

        if (!$foundHit) {
            $this->printer->printWarning("Coverage file '{$coverageFile}' does not contain any executed line. Looks like not a single test was executed.");
        }

        return $remappedCoverages;
    }

    /**
     * @throws ErrorException
     */
    private function createExtractor(string $coverageFile): CoverageExtractor
    {
        $format = $this->coverageFormatDetector->detectFormat($coverageFile);

        return match ($format) {
            CoverageInputFormat::Php => new PhpUnitCoverageExtractor(),
            CoverageInputFormat::Clover => new CloverCoverageExtractor(new XmlLoader()),
            CoverageInputFormat::Cobertura => new CoberturaCoverageExtractor(new XmlLoader()),
        };
    }

    private function mapCoverageFilePath(
        Config $config,
        string $filePath,
    ): string
    {
        foreach ($config->getCoveragePathMapping() as $oldPath => $newPath) {
            if (str_starts_with($filePath, $oldPath)) {
                return $newPath . substr($filePath, strlen($oldPath));
            }
        }

        return $filePath;
    }

}
