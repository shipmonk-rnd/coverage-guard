<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\StreamTestTrait;
use function range;
use function realpath;

final class PatchParserTest extends TestCase
{

    use StreamTestTrait;

    public function testIgnoresNoNewlineAtEndOfFileMarker(): void
    {
        $config = new Config();
        $config->setGitRoot(__DIR__ . '/../../');

        $patchParser = new PatchParser(__DIR__, new Printer($this->createStream(), noColor: true));
        $changes = $patchParser->getPatchChangedLines(__DIR__ . '/../_fixtures/PatchParser/no-newline-at-eof.patch', $config);

        $changedFile = realpath(__DIR__ . '/../_fixtures/PatchParser/NoNewlineAtEof.php');
        self::assertNotFalse($changedFile);

        self::assertSame([$changedFile => range(11, 16)], $changes);
    }

}
