<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShipMonk\CoverageGuard\Ast\FileTraverser;
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

    public function testExcludersReduceChangedExecutableLines(): void
    {
        $coverageFile = __DIR__ . '/../_fixtures/PatchCoverage/clover.xml';
        $patchFile = __DIR__ . '/../_fixtures/PatchCoverage/changes.patch';

        // without excluders: lines 12, 13 (uncovered throw), 16 are changed & executable
        $outputWithoutExcluders = $this->runCommand($coverageFile, $patchFile, __DIR__ . '/../_fixtures/PatchCoverage/config-without-excluders.php');
        self::assertStringContainsString('Changed executable lines: 3', $outputWithoutExcluders);
        self::assertStringContainsString('Coverage:                 66.67%', $outputWithoutExcluders);

        // with excluders: the throw line is excluded, leaving 2 covered lines
        $outputWithExcluders = $this->runCommand($coverageFile, $patchFile, __DIR__ . '/../_fixtures/PatchCoverage/config-with-excluders.php');
        self::assertStringContainsString('Changed executable lines: 2', $outputWithExcluders);
        self::assertStringContainsString('Coverage:                 100.00%', $outputWithExcluders);
    }

    private function runCommand(
        string $coverageFile,
        string $patchFile,
        string $configPath,
    ): string
    {
        $stdoutStream = $this->createStream();
        $stdoutPrinter = new Printer($stdoutStream, noColor: true);

        $command = $this->createCommand($stdoutPrinter);

        $exitCode = ($command)(
            $coverageFile,
            patchPath: $patchFile,
            configPath: $configPath,
        );
        self::assertSame(0, $exitCode);

        rewind($stdoutStream);
        $output = stream_get_contents($stdoutStream);
        fclose($stdoutStream);

        self::assertIsString($output);
        return $output;
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
        $fileTraverser = new FileTraverser((new ParserFactory())->createForHostVersion());
        return new PatchCoverageCommand($stdoutPrinter, $patchParser, $configResolver, new CoverageProvider(new CoverageFormatDetector(), $stderrPrinter), $fileTraverser);
    }

}
