<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use function fopen;
use function str_replace;
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
     * @return iterable<string, array{list<string>}>
     */
    public static function provideArgs(): iterable
    {
        yield 'with patch' => [[__DIR__ . '/fixtures/clover.xml', __DIR__ . '/fixtures/sample.patch']];
        yield 'without patch' => [[__DIR__ . '/fixtures/clover.xml']];

        yield 'with path mapping' => [
            [__DIR__ . '/fixtures/clover_with_absolute_paths.xml'],
            static function (Config $config): void {
                $config->addCoveragePathMapping('/some/ci/path/root', __DIR__ . '/..');
            },
        ];
    }

    public function testPatchIntegrityFailsWhenLineNumberExceedsFileLength(): void
    {
        $patchFile = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/fixtures/sample-line-out-of-bounds.patch');
        $sampleFile = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/fixtures/Sample.php');

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Patch file '{$patchFile}' refers to added line #98 of file '{$sampleFile}', but such line does not exist. Is the patch up-to-date?");

        $this->checkCoverageWithPatch($patchFile);
    }

    public function testPatchIntegrityFailsWhenAddedLineContentMismatches(): void
    {
        $patchFile = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/fixtures/sample-added-mismatch.patch');
        $sampleFile = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/fixtures/Sample.php');

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Patch file '{$patchFile}' has added line #15 that does not match actual content of file '{$sampleFile}'.\nExpected '        return 'goodbye';'\nFound '        return 'hello';'\n\nIs the patch up-to-date?");

        $this->checkCoverageWithPatch($patchFile);
    }

    public function testHandlesFileWithMissingNewlineAtEof(): void
    {
        $guard = $this->createCoverageGuard();
        $report = $guard->checkCoverage(__DIR__ . '/fixtures/clover-no-newline.xml');

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
            __DIR__ . '/fixtures/clover-windows.xml',
            __DIR__ . '/fixtures/sample-windows.patch',
        );
        $errors = $report->reportedErrors;

        self::assertCount(1, $errors);
        self::assertSame('Not 100% covered', $errors[0]->error->getMessage());
        self::assertStringEndsWith('SampleWindows.php', $errors[0]->codeBlock->getFilePath());
        self::assertSame(13, $errors[0]->codeBlock->getStartLineNumber());
    }

    private function checkCoverageWithPatch(string $patchFile): void
    {
        $guard = $this->createCoverageGuard();

        $guard->checkCoverage(
            __DIR__ . '/fixtures/clover.xml',
            $patchFile,
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

        return new CoverageGuard($config, $printer);
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
