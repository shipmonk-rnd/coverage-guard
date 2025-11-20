<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShipMonk\CoverageGuard\Coverage\CoverageFormatDetector;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\StreamTestTrait;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Utils\PatchParser;
use function fclose;
use function realpath;
use function rewind;
use function str_contains;
use function stream_get_contents;

final class PatchCoverageCommandTest extends TestCase
{

    use StreamTestTrait;

    public function testGetName(): void
    {
        $stdoutStream = $this->createStream();
        $stdoutPrinter = new Printer($stdoutStream, noColor: true);

        $command = $this->createCommand($stdoutPrinter);
        self::assertSame('patch-coverage', $command->getName());
    }

    public function testGetDescription(): void
    {
        $stdoutStream = $this->createStream();
        $stdoutPrinter = new Printer($stdoutStream, noColor: true);

        $command = $this->createCommand($stdoutPrinter);
        self::assertStringContainsString('coverage', $command->getDescription());
        self::assertStringContainsString('patch', $command->getDescription());
    }

    public function testInvokeWithNonExistentCoverageFile(): void
    {
        $stdoutStream = $this->createStream();
        $stdoutPrinter = new Printer($stdoutStream, noColor: true);

        $command = $this->createCommand($stdoutPrinter);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Coverage file not found');

        ($command)(
            'nonexistent.xml',
            patchPath: 'some.patch',
        );
    }

    public function testInvokeWithNonExistentPatchFile(): void
    {
        $stdoutStream = $this->createStream();
        $stdoutPrinter = new Printer($stdoutStream, noColor: true);

        $command = $this->createCommand($stdoutPrinter);

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
        $stdoutStream = $this->createStream();
        $stdoutPrinter = new Printer($stdoutStream, noColor: true);

        $command = $this->createCommand($stdoutPrinter);

        $coverageFile = __DIR__ . '/../_fixtures/clover.xml';
        $patchFile = __DIR__ . '/../_fixtures/sample.patch';

        $exitCode = ($command)(
            $coverageFile,
            patchPath: $patchFile,
        );

        self::assertSame(0, $exitCode);

        rewind($stdoutStream);
        $output = stream_get_contents($stdoutStream);
        fclose($stdoutStream);

        self::assertIsString($output);
        // The output should contain either coverage statistics or "No executable lines"
        self::assertTrue(
            str_contains($output, 'Patch Coverage Statistics:') || str_contains($output, 'No executable lines'),
            'Expected output to contain coverage stats or no-lines message',
        );
    }

    public function testInvokeWithNoPatchChanges(): void
    {
        $stdoutStream = $this->createStream();
        $stdoutPrinter = new Printer($stdoutStream, noColor: true);

        $command = $this->createCommand($stdoutPrinter);

        // Use a coverage file and patch that overlap (sample.patch adds untestedMethod to Sample.php)
        $coverageFile = __DIR__ . '/../_fixtures/clover_with_package.xml';
        $patchFile = __DIR__ . '/../_fixtures/sample.patch';

        $exitCode = ($command)(
            $coverageFile,
            patchPath: $patchFile,
        );

        self::assertSame(0, $exitCode);

        rewind($stdoutStream);
        $output = stream_get_contents($stdoutStream);
        fclose($stdoutStream);

        self::assertIsString($output);
        // The patch adds an untested method, so coverage should be reported
        self::assertStringContainsString('Patch Coverage Statistics:', $output);
        self::assertStringContainsString('Coverage:', $output);
    }

    private function createCommand(
        Printer $stdoutPrinter,
    ): PatchCoverageCommand
    {
        $gitRoot = realpath(__DIR__ . '/../..'); // Project root
        if ($gitRoot === false) {
            throw new RuntimeException('Failed to resolve git root');
        }
        $stderrStream = $this->createStream();
        $stderrPrinter = new Printer($stderrStream, noColor: true);
        $patchParser = new PatchParser($gitRoot, $stderrPrinter);
        $configResolver = new ConfigResolver($gitRoot);
        return new PatchCoverageCommand($stdoutPrinter, $patchParser, $configResolver, new CoverageProvider(new CoverageFormatDetector(), $stderrPrinter));
    }

}
