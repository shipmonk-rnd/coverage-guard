<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\TestCase;

class PhpSyntaxHighlighterTest extends TestCase
{

    public function testHighlightWithColors(): void
    {
        $highlighter = new PhpSyntaxHighlighter();
        $code = '$variable = "string";';
        $result = $highlighter->highlight($code);

        self::assertStringContainsString("\033[", $result); // Contains ANSI color codes
        self::assertStringContainsString('$variable', $result);
        self::assertStringContainsString('"string"', $result);
    }

}
