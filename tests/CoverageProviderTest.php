<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Coverage\CoverageFormatDetector;
use ShipMonk\CoverageGuard\Exception\ErrorException;

final class CoverageProviderTest extends TestCase
{

    use StreamTestTrait;

    public function testThrowsExceptionForNonExistentFile(): void
    {
        $stream = $this->createStream();
        $printer = new Printer($stream, noColor: true);
        $factory = new CoverageProvider(new CoverageFormatDetector(), $printer);
        $config = new Config();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Coverage file not found: /non/existent/file.xml');

        $factory->getCoverage($config, '/non/existent/file.xml');
    }

    public function testThrowsExceptionForUnknownFormat(): void
    {
        $stream = $this->createStream();
        $printer = new Printer($stream, noColor: true);
        $factory = new CoverageProvider(new CoverageFormatDetector(), $printer);
        $config = new Config();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageMatches("/Unknown coverage file format: '.*Sample\.php'\. Expecting \.cov or \.xml/");

        $factory->getCoverage($config, __DIR__ . '/_fixtures/Sample.php');
    }

}
