<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Excluder;

use LogicException;
use PHPUnit\Framework\TestCase;

final class ExclusionContextTest extends TestCase
{

    public function testGetters(): void
    {
        $context = new ExclusionContext('/path/to/file.php', [1 => '<?php', 2 => 'echo 1;']);

        self::assertSame('/path/to/file.php', $context->getFilePath());
        self::assertSame('echo 1;', $context->getLineContents(2));
    }

    public function testThrowsOnUnknownLine(): void
    {
        $context = new ExclusionContext('/path/to/file.php', [1 => '<?php']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Line number #2 of file '/path/to/file.php' is expected to exist.");

        $context->getLineContents(2);
    }

}
