<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use Composer\InstalledVersions;
use LogicException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use function count;
use function get_debug_type;
use function is_array;

final class PhpUnitCoverageExtractor implements CoverageExtractor
{

    /**
     * @return array<string, array<int, int>> file_path => [executable_line => hits]
     */
    public function getCoverage(string $coverageFile): array
    {
        if (!InstalledVersions::isInstalled('phpunit/php-code-coverage')) {
            throw new LogicException('In order to use .cov coverage files, you need to install phpunit/php-code-coverage');
        }

        $coverage = (static function (string $file): mixed {
            return include $file;
        })($coverageFile);

        if (!$coverage instanceof CodeCoverage) {
            throw new LogicException("Invalid coverage file: '{$coverageFile}'. Expected serialized CodeCoverage instance, got " . get_debug_type($coverage));
        }

        $result = [];
        $lineCoverage = $coverage->getData()->lineCoverage();

        foreach ($lineCoverage as $filePath => $fileCoverage) {
            if (!is_array($fileCoverage)) {
                throw new LogicException("Invalid coverage file: '{$coverageFile}'. Expected array under '{$filePath}' key, got " . get_debug_type($fileCoverage));
            }

            foreach ($fileCoverage as $lineNumber => $tests) {
                if (!is_array($tests)) {
                    throw new LogicException("Invalid coverage file: '{$coverageFile}'. Expected array under {$filePath} and #$lineNumber, got " . get_debug_type($tests));
                }

                $result[(string) $filePath][(int) $lineNumber] = count($tests);
            }
        }

        return $result;
    }

}
