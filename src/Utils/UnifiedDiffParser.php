<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use function array_pop;
use function count;
use function explode;
use function preg_match;
use function rtrim;
use function str_starts_with;
use function strpos;
use function substr;

/**
 * Strict parser of unified diffs produced by `git diff`.
 *
 * Hunk bodies are consumed by the line counts that the '@@' header declares.
 * A content line can therefore never be mistaken for a diff header,
 * no matter what text it starts with (e.g. added SQL comment '-- alternative ...').
 * Any structural inconsistency causes a hard error instead of a silent skip.
 */
final class UnifiedDiffParser
{

    /**
     * @return list<PatchFileDiff>
     *
     * @throws ErrorException
     */
    public function parse(
        string $patchContent,
        string $patchFile,
    ): array
    {
        $lines = explode("\n", $patchContent);

        foreach ($lines as $index => $line) {
            $lines[$index] = rtrim($line, "\r");
        }

        if ($lines[count($lines) - 1] === '') {
            array_pop($lines); // trailing newline
        }

        $fileDiffs = [];
        $index = 0;

        while (true) {
            $line = $lines[$index] ?? null;

            if ($line === null) {
                break;
            }

            if ($line === '') {
                $index++;
                continue;
            }

            if (str_starts_with($line, 'diff --git ')) {
                $index++;
                $targetPath = $this->parseSectionHeaders($lines, $index, $patchFile);
            } elseif (str_starts_with($line, '--- ')) {
                $targetPath = $this->parseFromToHeader($lines, $index, $patchFile);
            } else {
                throw $this->malformed($patchFile, $index, "unexpected line '{$line}'");
            }

            $addedLines = [];

            while (true) {
                $hunkHeader = $lines[$index] ?? null;

                if ($hunkHeader === null || !str_starts_with($hunkHeader, '@@ ')) {
                    break;
                }

                if ($targetPath === null) {
                    throw $this->malformed($patchFile, $index, "hunk without preceding '+++' header");
                }

                $this->parseHunk($lines, $index, $hunkHeader, $patchFile, $addedLines);
            }

            if ($targetPath !== null) {
                $fileDiffs[] = new PatchFileDiff($targetPath, $addedLines);
            }
        }

        return $fileDiffs;
    }

    /**
     * Consumes extended header lines of a 'diff --git' section.
     * Returns the target path, or null when the section has no text hunks
     * (mode-only change, rename-only, binary file).
     *
     * @param list<string> $lines
     *
     * @throws ErrorException
     */
    private function parseSectionHeaders(
        array $lines,
        int &$index,
        string $patchFile,
    ): ?string
    {
        while (true) {
            $line = $lines[$index] ?? null;

            if ($line === null) {
                return null;
            }

            if (str_starts_with($line, '--- ')) {
                return $this->parseFromToHeader($lines, $index, $patchFile);
            }

            if (str_starts_with($line, 'diff --git ') || str_starts_with($line, '@@ ')) {
                return null;
            }

            if ($line === 'GIT binary patch') {
                throw $this->malformed($patchFile, $index, 'binary patches are not supported, generate the patch without --binary');
            }

            if (preg_match('~^(old mode |new mode |deleted file mode |new file mode |copy from |copy to |rename from |rename to |similarity index |dissimilarity index |index |Binary files )~', $line) !== 1) {
                throw $this->malformed($patchFile, $index, "unexpected line '{$line}'");
            }

            $index++;
        }
    }

    /**
     * Parses the '--- old' + '+++ new' header pair and returns the target path.
     *
     * @param list<string> $lines
     *
     * @throws ErrorException
     */
    private function parseFromToHeader(
        array $lines,
        int &$index,
        string $patchFile,
    ): string
    {
        $nextLine = $lines[$index + 1] ?? null;

        if ($nextLine === null || !str_starts_with($nextLine, '+++ ')) {
            throw $this->malformed($patchFile, $index, "'---' header not followed by '+++' header");
        }

        $targetPath = substr($nextLine, 4);
        $tabPosition = strpos($targetPath, "\t");

        if ($tabPosition !== false) {
            $targetPath = substr($targetPath, 0, $tabPosition); // e.g. timestamp emitted by diff -u
        }

        if (str_starts_with($targetPath, '"')) {
            throw $this->malformed($patchFile, $index + 1, "quoted file paths are not supported, please rename '{$targetPath}'");
        }

        $index += 2;
        return $targetPath;
    }

    /**
     * Consumes one '@@' hunk and records its added lines.
     *
     * @param list<string> $lines
     * @param array<int, string> $addedLines
     *
     * @throws ErrorException
     */
    private function parseHunk(
        array $lines,
        int &$index,
        string $hunkHeader,
        string $patchFile,
        array &$addedLines,
    ): void
    {
        if (preg_match('~^@@ -\d+(?:,(?<oldCount>\d+))? \+(?<newStart>\d+)(?:,(?<newCount>\d+))? @@~', $hunkHeader, $match) !== 1) {
            throw $this->malformed($patchFile, $index, "invalid hunk header '{$hunkHeader}'");
        }

        $newCount = $match['newCount'] ?? '';
        $oldRemaining = $match['oldCount'] === '' ? 1 : (int) $match['oldCount'];
        $newRemaining = $newCount === '' ? 1 : (int) $newCount;
        $newLineNumber = (int) $match['newStart'];
        $index++;

        while ($oldRemaining > 0 || $newRemaining > 0) {
            $bodyLine = $lines[$index] ?? null;

            if ($bodyLine === null) {
                throw $this->malformed($patchFile, $index, 'patch ends in the middle of a hunk');
            }

            $prefix = $bodyLine === '' ? ' ' : $bodyLine[0]; // some tools strip the single space of an empty context line

            if ($prefix === '\\') { // "\ No newline at end of file"
                $index++;
                continue;
            }

            if ($prefix === ' ') {
                if ($oldRemaining === 0 || $newRemaining === 0) {
                    throw $this->malformed($patchFile, $index, 'hunk body does not match the line counts declared in its header');
                }

                $oldRemaining--;
                $newRemaining--;
                $newLineNumber++;
            } elseif ($prefix === '+') {
                if ($newRemaining === 0) {
                    throw $this->malformed($patchFile, $index, 'hunk body does not match the line counts declared in its header');
                }

                $newRemaining--;
                $addedLines[$newLineNumber] = substr($bodyLine, 1);
                $newLineNumber++;
            } elseif ($prefix === '-') {
                if ($oldRemaining === 0) {
                    throw $this->malformed($patchFile, $index, 'hunk body does not match the line counts declared in its header');
                }

                $oldRemaining--;
            } else {
                throw $this->malformed($patchFile, $index, "unexpected line '{$bodyLine}' inside a hunk");
            }

            $index++;
        }

        $trailingLine = $lines[$index] ?? null;

        if ($trailingLine !== null && str_starts_with($trailingLine, '\\')) { // trailing "\ No newline at end of file"
            $index++;
        }
    }

    private function malformed(
        string $patchFile,
        int $index,
        string $reason,
    ): ErrorException
    {
        $lineNumber = $index + 1;
        return new ErrorException("Patch file '{$patchFile}' is malformed at line #{$lineNumber}: {$reason}. Only output of 'git diff' is supported.");
    }

}
