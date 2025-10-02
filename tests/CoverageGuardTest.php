<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\TestCase;
use function getcwd;

class CoverageGuardTest extends TestCase
{

    public function testDetectsUntestedChangedMethod(): void
    {
        $cwd = getcwd();
        self::assertNotFalse($cwd);

        $guard = new CoverageGuard($cwd . '/');
        $guard->setStripPaths([$cwd . '/']);

        $untestedBlocks = $guard->checkCoverage(
            __DIR__ . '/fixtures/sample.patch',
            __DIR__ . '/fixtures/clover.xml',
        );

        self::assertCount(1, $untestedBlocks);
        self::assertSame(CodeBlockType::ClassMethod, $untestedBlocks[0]->type);
        self::assertStringEndsWith('Sample.php', $untestedBlocks[0]->path);
        self::assertSame(13, $untestedBlocks[0]->startLine);
        self::assertSame(16, $untestedBlocks[0]->endLine);
    }

}
