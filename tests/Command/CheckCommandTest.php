<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Utils\PatchParser;
use function fclose;
use function fopen;
use function rewind;
use function stream_get_contents;

final class CheckCommandTest extends TestCase
{

    public function testInvokeWithNonExistentCoverageFile(): void
    {
        $command = $this->createCommand();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Coverage file not found');

        ($command)('nonexistent.xml');
    }

    public function testInvokeWithValidCoverageFile(): void
    {
        $command = $this->createCommand();

        $coverageFile = __DIR__ . '/../_fixtures/clover_with_package.xml';
        $configFile = __DIR__ . '/../_fixtures/config-for-bintest-no-rule.php';

        $exitCode = ($command)(
            $coverageFile,
            configPath: $configFile,
        );

        self::assertSame(0, $exitCode);
    }

    public function testInvokeWithPatchOption(): void
    {
        $outputStream = $this->createStream();
        $command = $this->createCommand($outputStream);

        $coverageFile = __DIR__ . '/../_fixtures/clover.xml';
        $patchFile = __DIR__ . '/../_fixtures/sample.patch';
        $configFile = __DIR__ . '/../_fixtures/config-for-bintest.php';

        $exitCode = $command(
            $coverageFile,
            patchFile: $patchFile,
            configPath: $configFile,
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
        $command = $this->createCommand();

        $coverageFile = __DIR__ . '/../_fixtures/clover.xml';
        $patchFile = __DIR__ . '/../_fixtures/config-for-bintest.php'; // Valid file but wrong extension
        $configFile = __DIR__ . '/../_fixtures/config-for-bintest.php'; // Has git root set

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('expecting .patch or .diff extension');

        ($command)(
            $coverageFile,
            patchFile: $patchFile,
            configPath: $configFile,
        );
    }

    public function testInvokeWithInvalidConfigExtension(): void
    {
        $command = $this->createCommand();

        $coverageFile = __DIR__ . '/../_fixtures/clover.xml';
        $configFile = __DIR__ . '/../_fixtures/sample.patch'; // Valid file but wrong extension

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('must have php extension');

        ($command)(
            $coverageFile,
            configPath: $configFile,
        );
    }

    /**
     * @return resource
     */
    private function createStream(): mixed
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        return $stream;
    }

    /**
     * @param resource|null $stream
     */
    private function createCommand(mixed $stream = null): CheckCommand
    {
        $cwd = __DIR__;
        $printer = new Printer($stream ?? $this->createStream(), noColor: true);
        $configResolver = new ConfigResolver($cwd);
        return new CheckCommand($cwd, $printer, $configResolver, new PatchParser($cwd, $printer), new CoverageProvider($printer));
    }

}
