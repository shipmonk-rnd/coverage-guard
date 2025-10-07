<?php declare(strict_types = 1);

namespace Extractor;

use PHPUnit\Framework\TestCase;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\PhpUnitCoverageExtractor;
use function file_put_contents;
use function serialize;
use function sys_get_temp_dir;
use const PHP_EOL;

final class PhpUnitCoverageExtractorTest extends TestCase
{

    /**
     * @throws ErrorException
     */
    public function testValidCovFile(): void
    {
        $tmp = sys_get_temp_dir();
        $path = $tmp . '/coverage.cov';

        $driver = $this->createMock(Driver::class);
        $filter = new Filter();
        $data = new ProcessedCodeCoverageData();
        $data->setLineCoverage(['tests/fixtures/Sample.php' => [8 => []]]);

        $coverage = new CodeCoverage($driver, $filter);
        $coverage->setData($data);

        $php = "<?php return \unserialize(<<<'COV'" . PHP_EOL . serialize($coverage) . PHP_EOL . 'COV' . PHP_EOL . ');';
        file_put_contents($path, $php);

        $extractor = new PhpUnitCoverageExtractor();
        self::assertCount(1, $extractor->getCoverage($path));
    }

}
