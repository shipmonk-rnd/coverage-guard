<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use Composer\InstalledVersions;
use LogicException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use function count;
use function get_debug_type;
use function is_array;
use function is_int;
use function str_starts_with;
use function strlen;
use function substr;

final class PhpUnitCoverageExtractor implements CoverageExtractor
{

    /**
     * @param list<string> $stripPaths
     */
    public function __construct(
        private array $stripPaths = [],
    )
    {
    }

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
                continue;
            }

            $normalizedPath = $this->normalizePath((string) $filePath);

            foreach ($fileCoverage as $lineNumber => $tests) {
                if (!is_int($lineNumber) || !is_array($tests)) {
                    continue;
                }

                $result[$normalizedPath][$lineNumber] = count($tests);
            }
        }

        return $result;
    }

    private function normalizePath(string $filePath): string
    {
        foreach ($this->stripPaths as $stripPath) {
            if (str_starts_with($filePath, $stripPath)) {
                return substr($filePath, strlen($stripPath));
            }
        }

        return $filePath;
    }

}
