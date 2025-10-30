<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function fclose;
use function fopen;
use function rewind;
use function str_contains;
use function stream_get_contents;

final class PatchCoverageCommandTest extends TestCase
{

    public function testGetName(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $command = new PatchCoverageCommand($printer);
        self::assertSame('patch-coverage', $command->getName());
    }

    public function testGetDescription(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $command = new PatchCoverageCommand($printer);
        self::assertStringContainsString('coverage', $command->getDescription());
        self::assertStringContainsString('patch', $command->getDescription());
    }

    public function testInvokeWithNonExistentCoverageFile(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $command = new PatchCoverageCommand($printer);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Coverage file not found');

        ($command)(
            'nonexistent.xml',
            patch: 'some.patch',
        );
    }

    public function testInvokeWithNonExistentPatchFile(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $command = new PatchCoverageCommand($printer);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Patch file not found');

        ($command)(
            $coverageFile,
            patch: 'nonexistent.patch',
        );
    }

    public function testInvokeCalculatesPatchCoverage(): void
    {
        $outputStream = fopen('php://memory', 'w+');
        self::assertIsResource($outputStream);

        $printer = new Printer($outputStream, noColor: true);
        $command = new PatchCoverageCommand($printer);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';
        $patchFile = __DIR__ . '/../fixtures/sample.patch';

        $exitCode = ($command)(
            $coverageFile,
            patch: $patchFile,
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
        $outputStream = fopen('php://memory', 'w+');
        self::assertIsResource($outputStream);

        $printer = new Printer($outputStream, noColor: true);
        $command = new PatchCoverageCommand($printer);

        // Use a coverage file and patch that don't overlap
        $coverageFile = __DIR__ . '/../fixtures/clover_with_package.xml';
        $patchFile = __DIR__ . '/../fixtures/sample.patch'; // References different files

        $exitCode = ($command)(
            $coverageFile,
            patch: $patchFile,
        );

        self::assertSame(0, $exitCode);

        rewind($outputStream);
        $output = stream_get_contents($outputStream);
        fclose($outputStream);

        self::assertIsString($output);
        // Should show message about no executable lines
        self::assertStringContainsString('No executable lines found in patch', $output);
    }

}
