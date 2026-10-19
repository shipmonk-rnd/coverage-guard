<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use Composer\InstalledVersions;
use LogicException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function count;
use function get_debug_type;
use function is_array;
use function is_string;
use const DIRECTORY_SEPARATOR;

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

        if ($coverage instanceof CodeCoverage) { // php-code-coverage < 14
            $coverageData = $coverage->getData();
            $basePath = '';

        } elseif (is_array($coverage) && ($coverage['codeCoverage'] ?? null) instanceof ProcessedCodeCoverageData) { // php-code-coverage >= 14
            $coverageData = $coverage['codeCoverage'];
            $basePath = is_string($coverage['basePath'] ?? null) ? $coverage['basePath'] : '';

        } else {
            throw new ErrorException("Invalid coverage file: '{$coverageFile}'. Expected serialized CodeCoverage instance or array with 'codeCoverage' key, got " . get_debug_type($coverage));
        }

        $result = [];
        $lineCoverage = $coverageData->lineCoverage();

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

            // php-code-coverage >= 14 strips the common path prefix of all files and stores it as basePath
            $absoluteFilePath = $basePath === '' ? (string) $filePath : $basePath . DIRECTORY_SEPARATOR . $filePath;
            $result[] = new FileCoverage($absoluteFilePath, $executableLines);
        }

        return $result;
    }

}
