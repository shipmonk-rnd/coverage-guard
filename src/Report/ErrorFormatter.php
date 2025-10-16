<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Report;

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Hierarchy\LineOfCode;
use ShipMonk\CoverageGuard\PathHelper;
use ShipMonk\CoverageGuard\Printer;
use function count;
use function extension_loaded;
use function is_array;
use function ltrim;
use function min;
use function rtrim;
use function str_pad;
use function str_replace;
use function strlen;
use function substr;
use function token_get_all;
use const PHP_EOL;
use const PHP_INT_MAX;
use const STR_PAD_LEFT;
use const T_ABSTRACT;
use const T_AND_EQUAL;
use const T_ARRAY;
use const T_AS;
use const T_BREAK;
use const T_CALLABLE;
use const T_CASE;
use const T_CATCH;
use const T_CLASS;
use const T_CLASS_C;
use const T_CLONE;
use const T_COMMENT;
use const T_CONST;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_CONTINUE;
use const T_DECLARE;
use const T_DEFAULT;
use const T_DIR;
use const T_DO;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_ECHO;
use const T_ELSE;
use const T_ELSEIF;
use const T_EMPTY;
use const T_ENDDECLARE;
use const T_ENDFOR;
use const T_ENDFOREACH;
use const T_ENDIF;
use const T_ENDSWITCH;
use const T_ENDWHILE;
use const T_ENUM;
use const T_EVAL;
use const T_EXIT;
use const T_EXTENDS;
use const T_FILE;
use const T_FINAL;
use const T_FINALLY;
use const T_FN;
use const T_FOR;
use const T_FOREACH;
use const T_FUNC_C;
use const T_FUNCTION;
use const T_GLOBAL;
use const T_GOTO;
use const T_HALT_COMPILER;
use const T_IF;
use const T_IMPLEMENTS;
use const T_INCLUDE;
use const T_INCLUDE_ONCE;
use const T_INSTANCEOF;
use const T_INSTEADOF;
use const T_INTERFACE;
use const T_ISSET;
use const T_LINE;
use const T_LIST;
use const T_LNUMBER;
use const T_MATCH;
use const T_METHOD_C;
use const T_NAMESPACE;
use const T_NEW;
use const T_NS_C;
use const T_OBJECT_OPERATOR;
use const T_PRIVATE;
use const T_PROTECTED;
use const T_PUBLIC;
use const T_READONLY;
use const T_REQUIRE;
use const T_REQUIRE_ONCE;
use const T_RETURN;
use const T_STATIC;
use const T_SWITCH;
use const T_THROW;
use const T_TRAIT;
use const T_TRAIT_C;
use const T_TRY;
use const T_UNSET;
use const T_USE;
use const T_VAR;
use const T_VARIABLE;
use const T_WHILE;
use const T_YIELD;
use const T_YIELD_FROM;

final class ErrorFormatter
{

    private const COLOR_RESET = "\033[0m";
    private const COLOR_KEYWORD = "\033[95m"; // Bright magenta
    private const COLOR_STRING = "\033[92m"; // Bright green
    private const COLOR_COMMENT = "\033[90m"; // Dark gray
    private const COLOR_VARIABLE = "\033[96m"; // Bright cyan
    private const COLOR_NUMBER = "\033[93m"; // Bright yellow
    private const BG_COVERED = "\033[48;5;22m"; // Dark green background
    private const BG_UNCOVERED = "\033[48;5;52m"; // Dark red background

    private readonly PathHelper $pathHelper;

    private readonly Printer $printer;

    private readonly ?string $editorUrl;

    public function __construct(
        PathHelper $pathHelper,
        Printer $printer,
        Config $config,
    )
    {
        $this->pathHelper = $pathHelper;
        $this->printer = $printer;
        $this->editorUrl = $config->getEditorUrl();
    }

    public function formatReport(CoverageReport $report): int
    {
        $reportedErrors = $report->reportedErrors;
        $analysedFilesCount = count($report->analysedFiles);

        $this->printer->printLine('');

        if (count($reportedErrors) === 0) {
            $this->printer->printLine("✅ No violations found (analysed $analysedFilesCount files)");
            $this->printer->printLine('');
            return 0;
        }

        foreach ($reportedErrors as $reportedError) {
            $this->formatError($reportedError, $report->patchMode);
        }

        $this->printer->printLine('❌ Found ' . count($reportedErrors) . " violations (in $analysedFilesCount analysed files)");
        $this->printer->printLine('');

        return 1;
    }

    private function formatError(
        ReportedError $reportedError,
        bool $patchMode,
    ): void
    {
        $codeBlock = $reportedError->codeBlock;
        $coverageError = $reportedError->error;

        $relativePath = $this->pathHelper->relativizePath($codeBlock->getFilePath());
        $fileLocation = "{$relativePath}:{$codeBlock->getStartLineNumber()}";
        $clickableFileLocation = $this->makeClickable(
            $fileLocation,
            $codeBlock->getFilePath(),
            $codeBlock->getStartLineNumber(),
        );

        $this->printer->printLine('┌─────────────────────────────────────────────────────────────────────────────────');
        $this->printer->printLine("│ {$clickableFileLocation}");
        $this->printer->printLine('├─────────────────────────────────────────────────────────────────────────────────');
        $this->printer->printLine("│ {$coverageError->getMessage()}");
        $this->printer->printLine('├─────────────────────────────────────────────────────────────────────────────────');
        $this->printer->printLine($this->formatBlock($codeBlock, $patchMode));
        $this->printer->printLine('└─────────────────────────────────────────────────────────────────────────────────');
        $this->printer->printLine('');
        $this->printer->printLine('');
    }

    private function makeClickable(
        string $text,
        string $filePath,
        int $line,
    ): string
    {
        if ($this->editorUrl === null) {
            return $text;
        }

        $url = str_replace(
            ['{relFile}', '{file}', '{line}'],
            [$this->pathHelper->relativizePath($filePath), $filePath, (string) $line],
            $this->editorUrl,
        );

        // OSC 8 hyperlink
        return "\033]8;;{$url}\033\\{$text}\033]8;;\033\\";
    }

    private function formatBlock(
        CodeBlock $codeBlock,
        bool $patchMode,
    ): string
    {
        $lines = $codeBlock->getLines();

        // Calculate common leading whitespace to strip
        $minIndent = $this->calculateMinIndent($lines);

        $maxLineNumberWidth = strlen((string) $lines[count($lines) - 1]->getNumber());
        $output = '';

        foreach ($lines as $line) {
            $lineNumber = $line->getNumber();
            $lineContent = $line->getContents();
            $isChanged = $line->isChanged();
            $isCovered = $line->isCovered();
            $isExecutable = $line->isExecutable();

            // Format line number (right-aligned)
            $lineNumberFormatted = str_pad((string) $lineNumber, $maxLineNumberWidth, ' ', STR_PAD_LEFT);

            // Add background color if executable (when colors are enabled)
            $bgColor = '';
            if ($isExecutable && !$this->printer->hasDisabledColors()) {
                $bgColor = $isCovered ? self::BG_COVERED : self::BG_UNCOVERED;
            }

            // Add change indicator
            $changeIndicator = $patchMode && $isChanged ? '+' : ' ';

            // Coverage indicator (for plain text mode)
            $coverageIndicator = ' ';
            if ($isExecutable && $this->printer->hasDisabledColors()) {
                $coverageIndicator = $isCovered ? '|' : 'X';
            }

            // Strip common leading whitespace
            $trimmedContent = substr($lineContent, $minIndent);

            // Highlight the code content
            $highlightedContent = $this->highlightLine($trimmedContent);

            // Combine all parts: line number + change indicator + content
            $output .= '│ ';
            $output .= $bgColor . ' ' . $lineNumberFormatted . ' ' . self::COLOR_RESET;
            $output .= ' ' . $changeIndicator . ' ' . $coverageIndicator . ' ';
            $output .= $highlightedContent;
            $output .= PHP_EOL;
        }

        return rtrim($output, PHP_EOL);
    }

    private function highlightLine(
        string $lineContent,
    ): string
    {
        if (!extension_loaded('tokenizer') || $this->printer->hasDisabledColors()) {
            return $lineContent;
        }

        $tokens = token_get_all("<?php\n{$lineContent}");
        $output = '';
        $skipFirst = true;

        foreach ($tokens as $token) {
            // Skip the injected <?php tag
            if ($skipFirst) {
                $skipFirst = false;
                continue;
            }

            if (is_array($token)) {
                [$tokenType, $tokenValue] = $token;

                $output .= match (true) {
                    $tokenType === T_COMMENT || $tokenType === T_DOC_COMMENT => self::COLOR_COMMENT . $tokenValue . self::COLOR_RESET,
                    $tokenType === T_VARIABLE => self::COLOR_VARIABLE . $tokenValue . self::COLOR_RESET,
                    $tokenType === T_CONSTANT_ENCAPSED_STRING => self::COLOR_STRING . $tokenValue . self::COLOR_RESET,
                    $tokenType === T_LNUMBER => self::COLOR_NUMBER . $tokenValue . self::COLOR_RESET,
                    $this->isKeyword($tokenType) => self::COLOR_KEYWORD . $tokenValue . self::COLOR_RESET,
                    default => $tokenValue,
                };
            } else {
                $output .= $token;
            }
        }

        return rtrim($output, "\n");
    }

    /**
     * @param list<LineOfCode> $lines
     */
    private function calculateMinIndent(array $lines): int
    {
        $minIndent = PHP_INT_MAX;

        foreach ($lines as $line) {
            $content = $line->getContents();
            $trimmed = ltrim($content);

            // Skip empty or whitespace-only lines
            if ($trimmed === '') {
                continue;
            }

            $indent = strlen($content) - strlen($trimmed);
            $minIndent = min($minIndent, $indent);
        }

        return $minIndent === PHP_INT_MAX ? 0 : $minIndent;
    }

    private function isKeyword(int $tokenType): bool
    {
        return match ($tokenType) {
            T_ABSTRACT, T_AND_EQUAL, T_ARRAY, T_AS, T_BREAK, T_CALLABLE, T_CASE, T_CATCH,
            T_CLASS, T_CLASS_C, T_CLONE, T_CONST, T_CONTINUE, T_DECLARE, T_DEFAULT,
            T_DIR, T_DO, T_ECHO, T_ELSE, T_ELSEIF, T_EMPTY, T_ENDDECLARE, T_ENDFOR,
            T_ENDFOREACH, T_ENDIF, T_ENDSWITCH, T_ENDWHILE, T_EVAL, T_EXIT, T_EXTENDS,
            T_FILE, T_FINAL, T_FINALLY, T_FN, T_FOR, T_FOREACH, T_FUNCTION, T_FUNC_C,
            T_GLOBAL, T_GOTO, T_HALT_COMPILER, T_IF, T_IMPLEMENTS, T_INCLUDE,
            T_INCLUDE_ONCE, T_INSTANCEOF, T_INSTEADOF, T_INTERFACE, T_ISSET, T_LINE,
            T_LIST, T_MATCH, T_METHOD_C, T_NAMESPACE, T_NEW, T_NS_C, T_PRIVATE,
            T_PROTECTED, T_PUBLIC, T_READONLY, T_REQUIRE, T_REQUIRE_ONCE, T_RETURN,
            T_STATIC, T_SWITCH, T_THROW, T_TRAIT, T_TRAIT_C, T_TRY, T_UNSET, T_USE,
            T_VAR, T_WHILE, T_YIELD, T_YIELD_FROM, T_ENUM,
            T_DOUBLE_COLON, T_OBJECT_OPERATOR => true,
            default => false,
        };
    }

}
