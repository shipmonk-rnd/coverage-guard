<?php declare(strict_types = 1);

namespace Report;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\LineOfCode;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Report\CoverageReport;
use ShipMonk\CoverageGuard\Report\ErrorFormatter;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\ReportedError;
use function fopen;
use function rewind;
use function stream_get_contents;

class ErrorFormatterTest extends TestCase
{

    public function testHighlightWithColors(): void
    {
        $stream = fopen('php://memory', 'rw');
        self::assertNotFalse($stream);

        $highlighter = new ErrorFormatter(
            '/tmp',
            new Printer($stream, noColor: false),
        );

        $lines = [
            new LineOfCode(1, false, false, false, '<?php'),
            new LineOfCode(2, true, true, false, '$variable = "string";'),
            new LineOfCode(3, true, false, true, 'return $variable ? true : false;'),
        ];

        $codeBlock = new ClassMethodBlock(
            'TestClass',
            'testMethod',
            '/tmp/test.php',
            $lines,
        );
        $reportedError = new ReportedError(
            $codeBlock,
            CoverageError::message('Message'),
        );
        $report = new CoverageReport([$reportedError], ['/tmp/test.php'], patchMode: true);

        $highlighter->formatReport($report);
        rewind($stream);
        $result = stream_get_contents($stream);

        self::assertNotFalse($result);
        self::assertStringContainsString("\033[", $result); // Contains ANSI color codes
        self::assertStringContainsString('$variable', $result);
        self::assertStringContainsString('"string"', $result);
        self::assertStringContainsString('1', $result); // Line number
        self::assertStringContainsString('+', $result); // Change indicator
        self::assertStringContainsString('test.php', $result); // path was relativized
        self::assertStringNotContainsString('/tmp/test.php', $result); // path was relativized
    }

}
