<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use Composer\InstalledVersions;
use LogicException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function count;
use function get_debug_type;
use function is_array;

final class PhpUnitCoverageExtractor implements CoverageExtractor
{

    /**
     * @throws ErrorException
     */
    public function getCoverage(string $coverageFile): array
    {
        if (!InstalledVersions::isInstalled('phpunit/php-code-coverage')) {
            throw new ErrorException('In order to use .cov coverage files, you need to install phpunit/php-code-coverage');
        }

        $coverage = (static function (string $file): mixed {
            return include $file;
        })($coverageFile);

        if (!$coverage instanceof CodeCoverage) {
            throw new ErrorException("Invalid coverage file: '{$coverageFile}'. Expected serialized CodeCoverage instance, got " . get_debug_type($coverage));
        }

        $result = [];
        $lineCoverage = $coverage->getData(true)->lineCoverage();

        foreach ($lineCoverage as $filePath => $fileCoverage) {
            if (!is_array($fileCoverage)) {
                throw new LogicException("Invalid coverage file: '{$coverageFile}'. Expected array under '{$filePath}' key, got " . get_debug_type($fileCoverage));
            }

            $executableLines = [];
            foreach ($fileCoverage as $lineNumber => $tests) {
                if (!is_array($tests)) {
                    throw new LogicException("Invalid coverage file: '{$coverageFile}'. Expected array under {$filePath} and #$lineNumber, got " . get_debug_type($tests));
                }

                $executableLines[] = new ExecutableLine((int) $lineNumber, count($tests));
            }

            $result[] = new FileCoverage((string) $filePath, $executableLines);
        }

        return $result;
    }

}
