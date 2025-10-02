<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoverageGuardTest extends TestCase
{

    /**
     * @param list<string> $args
     */
    #[DataProvider('provideArgs')]
    public function testDetectsUntestedChangedMethod(array $args): void
    {
        $config = new Config();
        $config->setGitRoot(__DIR__ . '/../');
        $config->addStripPath(__DIR__ . '/../'); // since we cannot have absolute paths in clover.xml fixture, we use this to align the paths
        $guard = new CoverageGuard($config);

        $untestedBlocks = $guard->checkCoverage(...$args);

        self::assertCount(1, $untestedBlocks);
        self::assertSame(CodeBlockType::ClassMethod, $untestedBlocks[0]->type);
        self::assertStringEndsWith('Sample.php', $untestedBlocks[0]->path);
        self::assertSame(13, $untestedBlocks[0]->startLine);
        self::assertSame(16, $untestedBlocks[0]->endLine);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function provideArgs(): iterable
    {
        yield 'with patch' => [[__DIR__ . '/fixtures/clover.xml', __DIR__ . '/fixtures/sample.patch']];
        yield 'without patch' => [[__DIR__ . '/fixtures/clover.xml']];
    }

}
