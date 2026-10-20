<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use ShipMonk\CoverageGuard\Ast\FileTraverser;
use ShipMonk\CoverageGuard\Coverage\CoverageFormatDetector;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Excluder\IgnoreThrowNewExceptionLineExcluder;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\EnforceCoverageForMethodsRule;
use ShipMonk\CoverageGuard\Rule\InspectionContext;
use ShipMonk\CoverageGuard\Utils\PatchParser;
use ShipMonk\CoverageGuard\Utils\PathHelper;
use function file_put_contents;
use function realpath;
use function rewind;
use function serialize;
use function str_replace;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

final class CoverageGuardTest extends TestCase
{

    use StreamTestTrait;

    /**
     * @param array{string, ?string, bool} $args
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

        $guard = $this->createCoverageGuard();

        $report = $guard->checkCoverage($config, ...$args);
        $errors = $report->reportedErrors;

        self::assertCount(1, $errors);
        self::assertSame('Not 100% covered', $errors[0]->error->getMessage());
        self::assertStringEndsWith('Sample.php', $errors[0]->filePath);
        self::assertSame(13, $errors[0]->codeBlock->getStartLineNumber());
    }

    /**
     * @return iterable<string, array{array{string, string|null, bool}, 1?: callable(Config): void}>
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

        yield 'with path mapping with trailing slashes' => [
            [__DIR__ . '/_fixtures/CoverageGuardTest/clover_with_absolute_paths.xml', null, false],
            static function (Config $config): void {
                $config->addCoveragePathMapping('/some/ci/path/root/', __DIR__ . '/../');
            },
        ];
    }

    public function testExcludersAffectRuleResults(): void
    {
        $coverageFile = __DIR__ . '/_fixtures/PatchCoverage/clover.xml';
        $guard = $this->createCoverageGuard();

        $config = $this->createConfig();
        $config->addRule(new EnforceCoverageForMethodsRule(requiredCoveragePercentage: 100));

        $reportWithoutExcluders = $guard->checkCoverage($config, $coverageFile, null, false);
        self::assertCount(1, $reportWithoutExcluders->reportedErrors);
        self::assertStringContainsString('has only 67% coverage', $reportWithoutExcluders->reportedErrors[0]->error->getMessage());

        $config->addExecutableLineExcluder(new IgnoreThrowNewExceptionLineExcluder([LogicException::class]));

        $reportWithExcluders = $guard->checkCoverage($config, $coverageFile, null, false);
        self::assertCount(0, $reportWithExcluders->reportedErrors);
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
        $config = $this->createConfig();
        $guard = $this->createCoverageGuard();
        $report = $guard->checkCoverage($config, __DIR__ . '/_fixtures/CoverageGuardTest/clover-no-newline.xml', patchFile: null, verbose: true);

        // The test passes if no exception is thrown about line count mismatch
        self::assertSame([], $report->reportedErrors);
        self::assertCount(1, $report->analysedFiles);
        self::assertFalse($report->patchMode);
    }

    public function testHandlesWindowsNewlines(): void
    {
        $config = $this->createConfig();
        $config->addRule($this->createFullyUncoveredAndFullyChangedRule());

        $guard = $this->createCoverageGuard();

        $report = $guard->checkCoverage(
            $config,
            __DIR__ . '/_fixtures/CoverageGuardTest/clover-windows.xml',
            __DIR__ . '/_fixtures/CoverageGuardTest/sample-windows.patch',
            false,
        );
        $errors = $report->reportedErrors;

        self::assertCount(1, $errors);
        self::assertSame('Not 100% covered', $errors[0]->error->getMessage());
        self::assertStringEndsWith('SampleWindows.php', $errors[0]->filePath);
        self::assertSame(13, $errors[0]->codeBlock->getStartLineNumber());
    }

    public function testVerboseModeWithoutPatchShowsFilesAndCoverage(): void
    {
        $stream = $this->createStream();
        $printer = new Printer($stream, noColor: true); // No color for easier assertions
        $config = $this->createConfig();
        $guard = $this->createCoverageGuard(printer: $printer);

        $guard->checkCoverage($config, __DIR__ . '/_fixtures/clover.xml', patchFile: null, verbose: true);

        rewind($stream);
        $output = stream_get_contents($stream);
        self::assertNotFalse($output);

        self::assertStringContainsString('Info: Checking files listed in coverage report', $output);
        self::assertStringContainsString('tests/_fixtures/Sample.php', $output);
        self::assertStringContainsString('%', $output); // Coverage percentage
    }

    public function testVerboseModeWithPatchShowsFilesAndSkipped(): void
    {
        $stream = $this->createStream();
        $printer = new Printer($stream, noColor: true); // No color for easier assertions
        $config = $this->createConfig();
        $guard = $this->createCoverageGuard(printer: $printer);

        $guard->checkCoverage($config, __DIR__ . '/_fixtures/clover.xml', __DIR__ . '/_fixtures/sample.patch', verbose: true);

        rewind($stream);
        $output = stream_get_contents($stream);
        self::assertNotFalse($output);

        self::assertStringContainsString('Info: Checking files listed in patch file', $output);
        self::assertStringContainsString('tests/_fixtures/Sample.php', $output);
        self::assertStringContainsString('%', $output); // Coverage percentage
    }

    #[DataProvider('provideVerbose')]
    public function testSkipsFilesWithoutExecutableLines(bool $verbose): void
    {
        $sampleFile = realpath(__DIR__ . '/_fixtures/Sample.php');
        $interfaceFile = realpath(__DIR__ . '/_fixtures/CoverageGuardTest/SampleInterface.php');
        $emptyFile = tempnam(sys_get_temp_dir(), 'empty');

        self::assertNotFalse($sampleFile);
        self::assertNotFalse($interfaceFile);
        self::assertNotFalse($emptyFile);

        $data = new ProcessedCodeCoverageData();
        $data->setLineCoverage([
            $sampleFile => [10 => [], 15 => []],
            $interfaceFile => [],
            $emptyFile => [],
        ]);

        $coverageFile = $this->writeCovFile([
            'buildInformation' => [],
            'basePath' => '',
            'codeCoverage' => $data,
            'testResults' => [],
        ]);

        $report = $this->createCoverageGuard()->checkCoverage($this->createConfig(), $coverageFile, patchFile: null, verbose: $verbose);

        self::assertSame([$sampleFile], $report->analysedFiles);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideVerbose(): iterable
    {
        yield 'verbose' => [true];
        yield 'not verbose' => [false];
    }

    private function writeCovFile(mixed $coverage): string
    {
        $path = tempnam(sys_get_temp_dir(), 'coverage');
        self::assertNotFalse($path);

        $covPath = $path . '.cov';
        $php = "<?php return \unserialize(<<<'COV'" . PHP_EOL . serialize($coverage) . PHP_EOL . 'COV' . PHP_EOL . ');';
        file_put_contents($covPath, $php);

        return $covPath;
    }

    private function checkCoverageWithPatch(string $patchFile): void
    {
        $config = $this->createConfig();
        $guard = $this->createCoverageGuard();

        $guard->checkCoverage(
            $config,
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

    private function createCoverageGuard(
        ?Printer $printer = null,
    ): CoverageGuard
    {
        $cwd = __DIR__ . '/../';
        $printer ??= $this->createPrinter();
        $pathHelper = new PathHelper($cwd);
        $phpParser = (new ParserFactory())->createForHostVersion();
        $patchParser = new PatchParser($cwd, $printer);
        $fileTraverser = new FileTraverser($phpParser);
        $coverageProvider = new CoverageProvider(new CoverageFormatDetector(), $printer);
        $stopwatch = $this->createStopwatchStub();

        return new CoverageGuard($printer, $fileTraverser, $pathHelper, $patchParser, $coverageProvider, $stopwatch);
    }

    private function createStopwatchStub(): Stopwatch&Stub
    {
        $stopwatch = self::createStub(Stopwatch::class);
        $stopwatch->method('stop')->willReturn(0.0);

        return $stopwatch;
    }

    private function createPrinter(bool $noColor = false): Printer
    {
        $stream = $this->createStream();
        return new Printer($stream, noColor: $noColor);
    }

    private function createFullyUncoveredAndFullyChangedRule(): CoverageRule
    {
        return new class implements CoverageRule {

            public function inspect(
                CodeBlock $codeBlock,
                InspectionContext $context,
            ): ?CoverageError
            {
                if (
                    $codeBlock->getCoveragePercentage() === 0
                    && $codeBlock->getChangePercentage() === 100
                ) {
                    return CoverageError::create('Not 100% covered');
                }

                return null;
            }

        };
    }

}
