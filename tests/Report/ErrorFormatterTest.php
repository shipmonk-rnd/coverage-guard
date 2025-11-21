<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Report;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\LineOfCode;
use ShipMonk\CoverageGuard\PathHelper;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\StreamTestTrait;
use function rewind;
use function str_replace;
use function stream_get_contents;
use const DIRECTORY_SEPARATOR;

final class ErrorFormatterTest extends TestCase
{

    use StreamTestTrait;

    public function testHighlightWithColors(): void
    {
        $lines = [
            new LineOfCode(1, false, false, false, '<?php'),
            new LineOfCode(2, true, true, false, '$variable = "string";'),
            new LineOfCode(3, true, false, true, 'return $variable ? true : false;'),
        ];

        $result = $this->formatReport($lines, new Config(), patchMode: true);

        self::assertStringContainsString("\033[", $result); // Contains ANSI color codes
        self::assertStringContainsString('$variable', $result);
        self::assertStringContainsString('"string"', $result);
        self::assertStringContainsString('1', $result); // Line number
        self::assertStringContainsString('+', $result); // Change indicator
        self::assertStringContainsString('test.php', $result); // path was relativized
        self::assertStringNotContainsString(str_replace('/', DIRECTORY_SEPARATOR, '/tmp/test.php'), $result); // path was relativized
    }

    public function testClickableFilepathWhenEditorUrlSet(): void
    {
        $config = new Config();
        $config->setEditorUrl('vscode://file/{file}:{line}');

        $lines = [
            new LineOfCode(1, false, false, false, '<?php'),
            new LineOfCode(2, true, true, false, '$variable = "string";'),
        ];

        $result = $this->formatReport($lines, $config, patchMode: false);

        self::assertStringContainsString("\033]8;;", $result); // Contains OSC 8 hyperlink
        self::assertStringContainsString('vscode://file/', $result);
        self::assertStringContainsString('/tmp/test.php:1', $result); // File path is not encoded
        self::assertStringContainsString('test.php:1', $result); // Display text
    }

    public function testNoClickableFilepathWhenEditorUrlNotSet(): void
    {
        $lines = [
            new LineOfCode(1, false, false, false, '<?php'),
            new LineOfCode(2, true, true, false, '$variable = "string";'),
        ];

        $result = $this->formatReport($lines, new Config(), patchMode: false);

        self::assertStringNotContainsString("\033]8;;", $result); // No OSC 8 hyperlink
        self::assertStringContainsString('test.php:1', $result);
    }

    /**
     * @param non-empty-list<LineOfCode> $lines
     */
    private function formatReport(
        array $lines,
        Config $config,
        bool $patchMode,
    ): string
    {
        $stream = $this->createStream();
        $formatter = $this->createErrorFormatter($stream);
        $report = $this->createCoverageReport($lines, patchMode: $patchMode);

        $formatter->formatReport($report, $config->getEditorUrl());

        return $this->getStreamContents($stream);
    }

    /**
     * @param resource $stream
     */
    private function createErrorFormatter(
        $stream,
    ): ErrorFormatter
    {
        return new ErrorFormatter(
            new PathHelper('/tmp'),
            new Printer($stream, noColor: false),
        );
    }

    /**
     * @param non-empty-list<LineOfCode> $lines
     */
    private function createCoverageReport(
        array $lines,
        bool $patchMode,
    ): CoverageReport
    {
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

        return new CoverageReport([$reportedError], ['/tmp/test.php'], patchMode: $patchMode, elapsedTime: 0.0);
    }

    /**
     * @param resource $stream
     */
    private function getStreamContents($stream): string
    {
        rewind($stream);
        $result = stream_get_contents($stream);
        self::assertNotFalse($result);

        return $result;
    }

}
