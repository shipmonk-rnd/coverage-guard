<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Utils\PatchParser;
use function fclose;
use function realpath;
use function rewind;
use function str_contains;
use function stream_get_contents;

final class PatchCoverageCommandTest extends TestCase
{

    use CommandTestTrait;

    public function testGetName(): void
    {
        $stream = $this->createStream();
        $printer = new Printer($stream, noColor: true);

        $command = $this->createCommand($printer);
        self::assertSame('patch-coverage', $command->getName());
    }

    public function testGetDescription(): void
    {
        $stream = $this->createStream();
        $printer = new Printer($stream, noColor: true);

        $command = $this->createCommand($printer);
        self::assertStringContainsString('coverage', $command->getDescription());
        self::assertStringContainsString('patch', $command->getDescription());
    }

    public function testInvokeWithNonExistentCoverageFile(): void
    {
        $stream = $this->createStream();
        $printer = new Printer($stream, noColor: true);

        $command = $this->createCommand($printer);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Coverage file not found');

        ($command)(
            'nonexistent.xml',
            patchPath: 'some.patch',
        );
    }

    public function testInvokeWithNonExistentPatchFile(): void
    {
        $stream = $this->createStream();
        $printer = new Printer($stream, noColor: true);

        $command = $this->createCommand($printer);

        $coverageFile = __DIR__ . '/../_fixtures/clover.xml';

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Patch file not found');

        ($command)(
            $coverageFile,
            patchPath: 'nonexistent.patch',
        );
    }

    public function testInvokeCalculatesPatchCoverage(): void
    {
        $outputStream = $this->createStream();

        $printer = new Printer($outputStream, noColor: true);
        $command = $this->createCommand($printer);

        $coverageFile = __DIR__ . '/../_fixtures/clover.xml';
        $patchFile = __DIR__ . '/../_fixtures/sample.patch';

        $exitCode = ($command)(
            $coverageFile,
            patchPath: $patchFile,
        );

        self::assertSame(0, $exitCode);

        rewind($outputStream);
        $output = stream_get_contents($outputStream);
        fclose($outputStream);

        self::assertIsString($output);
        // The output should contain either coverage statistics or "No executable lines"
        self::assertTrue(
            str_contains($output, 'Patch Coverage Statistics:') || str_contains($output, 'No executable lines'),
            'Expected output to contain coverage stats or no-lines message',
        );
    }

    public function testInvokeWithNoPatchChanges(): void
    {
        $outputStream = $this->createStream();

        $printer = new Printer($outputStream, noColor: true);
        $command = $this->createCommand($printer);

        // Use a coverage file and patch that overlap (sample.patch adds untestedMethod to Sample.php)
        $coverageFile = __DIR__ . '/../_fixtures/clover_with_package.xml';
        $patchFile = __DIR__ . '/../_fixtures/sample.patch';

        $exitCode = ($command)(
            $coverageFile,
            patchPath: $patchFile,
        );

        self::assertSame(0, $exitCode);

        rewind($outputStream);
        $output = stream_get_contents($outputStream);
        fclose($outputStream);

        self::assertIsString($output);
        // The patch adds an untested method, so coverage should be reported
        self::assertStringContainsString('Patch Coverage Statistics:', $output);
        self::assertStringContainsString('Coverage:', $output);
    }

    private function createCommand(Printer $printer): PatchCoverageCommand
    {
        $gitRoot = realpath(__DIR__ . '/../..'); // Project root
        if ($gitRoot === false) {
            throw new RuntimeException('Failed to resolve git root');
        }
        $patchParser = new PatchParser($gitRoot, $printer);
        $configResolver = new ConfigResolver($gitRoot);
        return new PatchCoverageCommand($printer, $patchParser, $configResolver, new CoverageProvider($printer));
    }

}
