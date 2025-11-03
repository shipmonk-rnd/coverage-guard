<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use function fopen;
use function rewind;
use function str_replace;
use function stream_get_contents;
use const DIRECTORY_SEPARATOR;

final class CoverageGuardTest extends TestCase
{

    /**
     * @param list<string> $args
     * @param (callable(Config): void)|null $adjustConfig
     */
    #[DataProvider('provideArgs')]
    public function testDetectsUntestedChangedMethod(
        array $args,
        ?callable $adjustConfig = null,
    ): void
    {
        $config = $this->createConfig();
        $config->addRule($this->createFullyUncoveredAndFullyChangedRule());

        if ($adjustConfig !== null) {
            $adjustConfig($config);
        }

        $guard = $this->createCoverageGuard($config);

        $report = $guard->checkCoverage(...$args);
        $errors = $report->reportedErrors;

        self::assertCount(1, $errors);
        self::assertSame('Not 100% covered', $errors[0]->error->getMessage());
        self::assertStringEndsWith('Sample.php', $errors[0]->codeBlock->getFilePath());
        self::assertSame(13, $errors[0]->codeBlock->getStartLineNumber());
    }

    /**
     * @return iterable<string, array{array{string, string|null, bool}}>
     */
    public static function provideArgs(): iterable
    {
        yield 'with patch' => [[__DIR__ . '/_fixtures/clover.xml', __DIR__ . '/_fixtures/sample.patch', false]];
        yield 'without patch' => [[__DIR__ . '/_fixtures/clover.xml', null, false]];

        yield 'with path mapping' => [
            [__DIR__ . '/_fixtures/CoverageGuardTest/clover_with_absolute_paths.xml', null, false],
            static function (Config $config): void {
                $config->addCoveragePathMapping('/some/ci/path/root', __DIR__ . '/..');
            },
        ];
    }

    public function testPatchIntegrityFailsWhenLineNumberExceedsFileLength(): void
    {
        $patchFile = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/_fixtures/CoverageGuardTest/sample-line-out-of-bounds.patch');
        $sampleFile = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/_fixtures/Sample.php');

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Patch file '{$patchFile}' refers to added line #98 with '    public function untestedMethod(): string' contents in file '{$sampleFile}', but such line does not exist. Is the patch up-to-date?");

        $this->checkCoverageWithPatch($patchFile);
    }

    public function testPatchIntegrityFailsWhenAddedLineContentMismatches(): void
    {
        $patchFile = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/_fixtures/CoverageGuardTest/sample-added-mismatch.patch');
        $sampleFile = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/_fixtures/Sample.php');

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Patch file '{$patchFile}' has added line #15 that does not match actual content of file '{$sampleFile}'.\nPatch data: '        return 'goodbye';'\nFilesystem: '        return 'hello';'\n\nIs the patch up-to-date?");

        $this->checkCoverageWithPatch($patchFile);
    }

    public function testHandlesFileWithMissingNewlineAtEof(): void
    {
        $guard = $this->createCoverageGuard();
        $report = $guard->checkCoverage(__DIR__ . '/_fixtures/CoverageGuardTest/clover-no-newline.xml', patchFile: null, verbose: true);

        // The test passes if no exception is thrown about line count mismatch
        self::assertSame([], $report->reportedErrors);
        self::assertCount(1, $report->analysedFiles);
        self::assertFalse($report->patchMode);
    }

    public function testHandlesWindowsNewlines(): void
    {
        $config = $this->createConfig();
        $config->addRule($this->createFullyUncoveredAndFullyChangedRule());

        $guard = $this->createCoverageGuard($config);

        $report = $guard->checkCoverage(
            __DIR__ . '/_fixtures/CoverageGuardTest/clover-windows.xml',
            __DIR__ . '/_fixtures/CoverageGuardTest/sample-windows.patch',
            false,
        );
        $errors = $report->reportedErrors;

        self::assertCount(1, $errors);
        self::assertSame('Not 100% covered', $errors[0]->error->getMessage());
        self::assertStringEndsWith('SampleWindows.php', $errors[0]->codeBlock->getFilePath());
        self::assertSame(13, $errors[0]->codeBlock->getStartLineNumber());
    }

    public function testVerboseModeWithoutPatchShowsFilesAndCoverage(): void
    {
        $stream = fopen('php://memory', 'rw');
        self::assertNotFalse($stream);

        $printer = new Printer($stream, noColor: true); // No color for easier assertions
        $config = $this->createConfig();
        $pathHelper = new PathHelper(__DIR__ . '/../');
        $guard = new CoverageGuard($config, $printer, $pathHelper);

        $guard->checkCoverage(__DIR__ . '/_fixtures/clover.xml', patchFile: null, verbose: true);

        rewind($stream);
        $output = stream_get_contents($stream);
        self::assertNotFalse($output);

        self::assertStringContainsString('Info: Checking files listed in coverage report', $output);
        self::assertStringContainsString('tests/_fixtures/Sample.php', $output);
        self::assertStringContainsString('%', $output); // Coverage percentage
    }

    public function testVerboseModeWithPatchShowsFilesAndSkipped(): void
    {
        $stream = fopen('php://memory', 'rw');
        self::assertNotFalse($stream);

        $printer = new Printer($stream, noColor: true); // No color for easier assertions
        $config = $this->createConfig();
        $pathHelper = new PathHelper(__DIR__ . '/../');
        $guard = new CoverageGuard($config, $printer, $pathHelper);

        $guard->checkCoverage(__DIR__ . '/_fixtures/clover.xml', __DIR__ . '/_fixtures/sample.patch', verbose: true);

        rewind($stream);
        $output = stream_get_contents($stream);
        self::assertNotFalse($output);

        self::assertStringContainsString('Info: Checking files listed in patch file', $output);
        self::assertStringContainsString('tests/_fixtures/Sample.php', $output);
        self::assertStringContainsString('%', $output); // Coverage percentage
    }

    private function checkCoverageWithPatch(string $patchFile): void
    {
        $guard = $this->createCoverageGuard();

        $guard->checkCoverage(
            __DIR__ . '/_fixtures/clover.xml',
            $patchFile,
            false,
        );
    }

    private function createConfig(): Config
    {
        $config = new Config();
        $config->setGitRoot(__DIR__ . '/../');
        return $config;
    }

    private function createCoverageGuard(?Config $config = null): CoverageGuard
    {
        $stream = fopen('php://memory', 'rw');
        self::assertNotFalse($stream);

        $printer = new Printer($stream, noColor: false);
        $config ??= $this->createConfig();
        $pathHelper = new PathHelper(__DIR__ . '/../');

        return new CoverageGuard($config, $printer, $pathHelper);
    }

    private function createFullyUncoveredAndFullyChangedRule(): CoverageRule
    {
        return new class implements CoverageRule {

            public function inspect(
                CodeBlock $codeBlock,
                bool $patchMode,
            ): ?CoverageError
            {
                if (
                    $codeBlock->isFullyUncovered()
                    && $codeBlock->isFullyChanged()
                ) {
                    return CoverageError::message('Not 100% covered');
                }

                return null;
            }

        };
    }

}
