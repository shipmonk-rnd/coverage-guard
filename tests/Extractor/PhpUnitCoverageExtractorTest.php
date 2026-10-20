<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use PHPUnit\Framework\TestCase;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function file_put_contents;
use function serialize;
use function sys_get_temp_dir;
use function tempnam;
use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

final class PhpUnitCoverageExtractorTest extends TestCase
{

    public function testValidCovFile(): void
    {
        $driver = self::createStub(Driver::class);
        $filter = new Filter();
        $data = new ProcessedCodeCoverageData();
        $data->setLineCoverage(['tests/_fixtures/Sample.php' => [8 => []]]);

        $coverage = new CodeCoverage($driver, $filter);
        $coverage->setData($data);

        $extractor = new PhpUnitCoverageExtractor();
        $result = $extractor->getCoverage($this->writeCovFile($coverage));

        self::assertCount(1, $result);
        self::assertSame('tests/_fixtures/Sample.php', $result[0]->filePath);
    }

    public function testValidCovFileFromPhpCodeCoverage14(): void
    {
        $data = new ProcessedCodeCoverageData();
        $data->setLineCoverage([
            'Sample.php' => [8 => [], 9 => []],
            'Extractor/Other.php' => [5 => []],
        ]);

        $extractor = new PhpUnitCoverageExtractor();
        $result = $extractor->getCoverage($this->writeCovFile([
            'buildInformation' => [],
            'basePath' => '/app/tests/_fixtures',
            'codeCoverage' => $data,
            'testResults' => [],
        ]));

        self::assertSame([
            '/app/tests/_fixtures' . DIRECTORY_SEPARATOR . 'Extractor/Other.php' => [5 => 0],
            '/app/tests/_fixtures' . DIRECTORY_SEPARATOR . 'Sample.php' => [8 => 0, 9 => 0],
        ], $this->toHitsMap($result));
    }

    public function testCovFileFromPhpCodeCoverage14WithEmptyBasePath(): void
    {
        $data = new ProcessedCodeCoverageData();
        $data->setLineCoverage(['tests/_fixtures/Sample.php' => [8 => []]]);

        $extractor = new PhpUnitCoverageExtractor();
        $result = $extractor->getCoverage($this->writeCovFile([
            'buildInformation' => [],
            'basePath' => '',
            'codeCoverage' => $data,
            'testResults' => [],
        ]));

        self::assertCount(1, $result);
        self::assertSame('tests/_fixtures/Sample.php', $result[0]->filePath);
    }

    public function testSkipsDeadLines(): void
    {
        $data = new ProcessedCodeCoverageData();
        $data->setLineCoverage(['Sample.php' => [8 => [], 9 => null, 10 => []]]);

        $extractor = new PhpUnitCoverageExtractor();
        $result = $extractor->getCoverage($this->writeCovFile([
            'buildInformation' => [],
            'basePath' => '',
            'codeCoverage' => $data,
            'testResults' => [],
        ]));

        self::assertSame(['Sample.php' => [8 => 0, 10 => 0]], $this->toHitsMap($result));
    }

    public function testInvalidCovFile(): void
    {
        $extractor = new PhpUnitCoverageExtractor();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Expected serialized CodeCoverage instance or array with 'codeCoverage' key, got array");

        $extractor->getCoverage($this->writeCovFile(['foo' => 'bar']));
    }

    /**
     * @param list<FileCoverage> $fileCoverages
     * @return array<string, array<int, int>>
     */
    private function toHitsMap(array $fileCoverages): array
    {
        $result = [];

        foreach ($fileCoverages as $fileCoverage) {
            foreach ($fileCoverage->executableLines as $executableLine) {
                $result[$fileCoverage->filePath][$executableLine->lineNumber] = $executableLine->hits;
            }
        }

        return $result;
    }

    private function writeCovFile(mixed $coverage): string
    {
        $path = tempnam(sys_get_temp_dir(), 'coverage');
        self::assertNotFalse($path);

        $php = "<?php return \unserialize(<<<'COV'" . PHP_EOL . serialize($coverage) . PHP_EOL . 'COV' . PHP_EOL . ');';
        file_put_contents($path, $php);

        return $path;
    }

}
