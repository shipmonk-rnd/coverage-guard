<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use function fopen;

class CoverageGuardTest extends TestCase
{

    /**
     * @param list<string> $args
     */
    #[DataProvider('provideArgs')]
    public function testDetectsUntestedChangedMethod(array $args): void
    {
        $stream = fopen('php://memory', 'rw');
        self::assertNotFalse($stream);

        $printer = new Printer($stream, noColor: false);
        $config = new Config();
        $config->setGitRoot(__DIR__ . '/../');
        $config->addStripPath(__DIR__ . '/../'); // since we cannot have absolute paths in clover.xml fixture, we use this to align the paths
        $config->addRule(new class implements CoverageRule {

            public function inspect(
                CodeBlock $codeBlock,
                bool $patchMode,
            ): ?CoverageError
            {
                if (!$codeBlock->isFullyUncovered()) {
                    return CoverageError::message('Not 100% covered');
                }

                return null;
            }

        });
        $guard = new CoverageGuard($config, $printer);

        $report = $guard->checkCoverage(...$args);
        $errors = $report->reportedErrors;

        self::assertCount(1, $errors);
        self::assertSame('Not 100% covered', $errors[0]->error->getMessage());
        self::assertStringEndsWith('Sample.php', $errors[0]->codeBlock->getFilePath());
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function provideArgs(): iterable
    {
        // yield 'with patch' => [[__DIR__ . '/fixtures/clover.xml', __DIR__ . '/fixtures/sample.patch']];
        yield 'without patch' => [[__DIR__ . '/fixtures/clover.xml']];
    }

}
