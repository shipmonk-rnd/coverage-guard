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
        $command = new PatchCoverageCommand();
        self::assertSame('patch-coverage', $command->getName());
    }

    public function testGetDescription(): void
    {
        $command = new PatchCoverageCommand();
        self::assertStringContainsString('coverage', $command->getDescription());
        self::assertStringContainsString('patch', $command->getDescription());
    }

    public function testGetArguments(): void
    {
        $command = new PatchCoverageCommand();
        $arguments = $command->getArguments();

        self::assertCount(1, $arguments);
        self::assertSame('coverage-file', $arguments[0]->name);
    }

    public function testGetOptions(): void
    {
        $command = new PatchCoverageCommand();
        $options = $command->getOptions();

        self::assertCount(1, $options);
        self::assertSame('patch', $options[0]->name);
        self::assertTrue($options[0]->requiresValue);
    }

    public function testExecuteRequiresPatchOption(): void
    {
        $command = new PatchCoverageCommand();

        $outputStream = fopen('php://memory', 'w+');
        self::assertIsResource($outputStream);

        $printer = new Printer($outputStream, noColor: true);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('--patch is required');

        $command->execute(
            [$coverageFile],
            [],
            $printer,
        );
    }

    public function testExecuteWithNonExistentCoverageFile(): void
    {
        $command = new PatchCoverageCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Coverage file not found');

        $command->execute(
            ['nonexistent.xml'],
            ['patch' => 'some.patch'],
            $printer,
        );
    }

    public function testExecuteWithNonExistentPatchFile(): void
    {
        $command = new PatchCoverageCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Patch file not found');

        $command->execute(
            [$coverageFile],
            ['patch' => 'nonexistent.patch'],
            $printer,
        );
    }

    public function testExecuteCalculatesPatchCoverage(): void
    {
        $command = new PatchCoverageCommand();

        $outputStream = fopen('php://memory', 'w+');
        self::assertIsResource($outputStream);

        $printer = new Printer($outputStream, noColor: true);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';
        $patchFile = __DIR__ . '/../fixtures/sample.patch';

        $exitCode = $command->execute(
            [$coverageFile],
            ['patch' => $patchFile],
            $printer,
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

    public function testExecuteWithNoPatchChanges(): void
    {
        $command = new PatchCoverageCommand();

        $outputStream = fopen('php://memory', 'w+');
        self::assertIsResource($outputStream);

        $printer = new Printer($outputStream, noColor: true);

        // Use a coverage file and patch that don't overlap
        $coverageFile = __DIR__ . '/../fixtures/clover_with_package.xml';
        $patchFile = __DIR__ . '/../fixtures/sample.patch'; // References different files

        $exitCode = $command->execute(
            [$coverageFile],
            ['patch' => $patchFile],
            $printer,
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
