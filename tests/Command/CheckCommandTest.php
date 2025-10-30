<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function fclose;
use function fopen;
use function rewind;
use function stream_get_contents;

final class CheckCommandTest extends TestCase
{

    public function testGetName(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $command = new CheckCommand($printer);
        self::assertSame('check', $command->getName());
    }

    public function testGetDescription(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $command = new CheckCommand($printer);
        self::assertStringContainsString('coverage', $command->getDescription());
    }

    public function testInvokeWithNonExistentCoverageFile(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $command = new CheckCommand($printer);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Coverage file not found');

        ($command)('nonexistent.xml');
    }

    public function testInvokeWithValidCoverageFile(): void
    {
        $outputStream = fopen('php://memory', 'w+');
        self::assertIsResource($outputStream);

        $printer = new Printer($outputStream, noColor: true);
        $command = new CheckCommand($printer);

        $coverageFile = __DIR__ . '/../fixtures/clover_with_package.xml';
        $configFile = __DIR__ . '/../fixtures/config-for-bintest-no-rule.php';

        $exitCode = ($command)(
            $coverageFile,
            config: $configFile,
        );

        self::assertSame(0, $exitCode);
    }

    public function testInvokeWithPatchOption(): void
    {
        $outputStream = fopen('php://memory', 'w+');
        self::assertIsResource($outputStream);

        $printer = new Printer($outputStream, noColor: true);
        $command = new CheckCommand($printer);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';
        $patchFile = __DIR__ . '/../fixtures/sample.patch';
        $configFile = __DIR__ . '/../fixtures/config-for-bintest.php';

        $exitCode = ($command)(
            $coverageFile,
            patch: $patchFile,
            config: $configFile,
        );

        self::assertSame(1, $exitCode); // Should find violations

        rewind($outputStream);
        $output = stream_get_contents($outputStream);
        fclose($outputStream);

        self::assertIsString($output);
        self::assertStringContainsString('Sample.php', $output);
    }

    public function testInvokeWithInvalidPatchExtension(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $command = new CheckCommand($printer);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';
        $patchFile = __DIR__ . '/../fixtures/config-for-bintest.php'; // Valid file but wrong extension

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('expecting .patch or .diff extension');

        ($command)(
            $coverageFile,
            patch: $patchFile,
        );
    }

    public function testInvokeWithInvalidConfigExtension(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $command = new CheckCommand($printer);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';
        $configFile = __DIR__ . '/../fixtures/sample.patch'; // Valid file but wrong extension

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('must have php extension');

        ($command)(
            $coverageFile,
            config: $configFile,
        );
    }

}
