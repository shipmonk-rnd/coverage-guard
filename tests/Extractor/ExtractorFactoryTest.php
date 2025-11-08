<?php declare(strict_types = 1);

namespace Extractor;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoberturaCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\ExtractorFactory;

final class ExtractorFactoryTest extends TestCase
{

    public function testCreatesCloverExtractor(): void
    {
        $factory = new ExtractorFactory();
        $extractor = $factory->createExtractor(__DIR__ . '/../_fixtures/clover.xml');

        self::assertInstanceOf(CloverCoverageExtractor::class, $extractor);
    }

    public function testCreatesCobertuaExtractor(): void
    {
        $factory = new ExtractorFactory();
        $extractor = $factory->createExtractor(__DIR__ . '/../_fixtures/Extractor/cobertura.xml');

        self::assertInstanceOf(CoberturaCoverageExtractor::class, $extractor);
    }

    public function testThrowsExceptionForNonExistentFile(): void
    {
        $factory = new ExtractorFactory();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Coverage file not found: /non/existent/file.xml');

        $factory->createExtractor('/non/existent/file.xml');
    }

    public function testThrowsExceptionForUnknownFormat(): void
    {
        $factory = new ExtractorFactory();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageMatches("/Unknown coverage file format: '.*Sample\.php'\. Expecting \.cov or \.xml/");

        $factory->createExtractor(__DIR__ . '/../_fixtures/Sample.php');
    }

}
