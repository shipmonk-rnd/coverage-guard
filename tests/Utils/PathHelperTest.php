<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function str_replace;
use const DIRECTORY_SEPARATOR;

final class PathHelperTest extends TestCase
{

    #[DataProvider('providePaths')]
    public function testRelativizePath(
        string $cwd,
        string $path,
        string $expectedPath,
    ): void
    {
        $pathHelper = new PathHelper($this->toNativePath($cwd));

        self::assertSame($expectedPath, $pathHelper->relativizePath($this->toNativePath($path)));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providePaths(): iterable
    {
        yield 'file in cwd' => ['/app', '/app/src/Foo.php', 'src/Foo.php'];
        yield 'cwd with trailing separator' => ['/app/', '/app/src/Foo.php', 'src/Foo.php'];
        yield 'cwd repeated inside path' => ['/app', '/app/src/Module/app/Foo.php', 'src/Module/app/Foo.php'];
        yield 'filesystem root as cwd' => ['/', '/srv/project/src/Foo.php', 'srv/project/src/Foo.php'];
        yield 'path outside cwd' => ['/app', '/other/app/Foo.php', '/other/app/Foo.php'];
        yield 'sibling directory with same prefix' => ['/app', '/application/Foo.php', '/application/Foo.php'];
    }

    private function toNativePath(string $path): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

}
