<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function implode;

final class UnifiedDiffParserTest extends TestCase
{

    public function testAddedSqlCommentLinesAreNotMistakenForHeaders(): void
    {
        // regression: heuristic parsers drop '+-- a...' lines as '--- a/...' headers
        $patch = self::linesToPatch([
            'diff --git a/migration.sql b/migration.sql',
            'new file mode 100644',
            'index 000000000..1f478b6f4',
            '--- /dev/null',
            '+++ b/migration.sql',
            '@@ -0,0 +1,4 @@',
            '+-- Some guard: the fallback pick fee family (insert-aware, formula-based',
            "+-- alternative to the ops-driven pick fees, port of the backend's",
            '+-- v2026_formula_based_with_insert_heuristics formula).',
            '+ALTER TYPE fee_code ADD VALUE \'some_fee\';',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(1, $fileDiffs);
        self::assertSame('b/migration.sql', $fileDiffs[0]->targetPath);
        self::assertSame([
            1 => '-- Some guard: the fallback pick fee family (insert-aware, formula-based',
            2 => "-- alternative to the ops-driven pick fees, port of the backend's",
            3 => '-- v2026_formula_based_with_insert_heuristics formula).',
            4 => 'ALTER TYPE fee_code ADD VALUE \'some_fee\';',
        ], $fileDiffs[0]->addedLines);
    }

    public function testDiffOfADiffIsNotMistakenForHeaders(): void
    {
        $patch = self::linesToPatch([
            'diff --git a/fixture.patch b/fixture.patch',
            'index 1111111..2222222 100644',
            '--- a/fixture.patch',
            '+++ b/fixture.patch',
            '@@ -1,3 +1,4 @@',
            ' --- a/inner.txt',
            ' +++ b/inner.txt',
            '+@@ -1,1 +1,2 @@',
            ' diff --git a/other.txt b/other.txt',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(1, $fileDiffs);
        self::assertSame([3 => '@@ -1,1 +1,2 @@'], $fileDiffs[0]->addedLines);
    }

    public function testMultipleFilesAndHunks(): void
    {
        $patch = self::linesToPatch([
            'diff --git a/first.txt b/first.txt',
            'index 1111111..2222222 100644',
            '--- a/first.txt',
            '+++ b/first.txt',
            '@@ -1,2 +1,3 @@',
            ' line1',
            '+line2',
            ' line3',
            '@@ -10,3 +11,2 @@ context info',
            ' line11',
            '-line12',
            ' line13',
            'diff --git a/second.txt b/second.txt',
            'index 3333333..4444444 100644',
            '--- a/second.txt',
            '+++ b/second.txt',
            '@@ -1 +1 @@',
            '-old',
            '+new',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(2, $fileDiffs);
        self::assertSame('b/first.txt', $fileDiffs[0]->targetPath);
        self::assertSame([2 => 'line2'], $fileDiffs[0]->addedLines);
        self::assertSame('b/second.txt', $fileDiffs[1]->targetPath);
        self::assertSame([1 => 'new'], $fileDiffs[1]->addedLines);
    }

    public function testDeletedFileHasDevNullTarget(): void
    {
        $patch = self::linesToPatch([
            'diff --git a/gone.txt b/gone.txt',
            'deleted file mode 100644',
            'index 1111111..0000000',
            '--- a/gone.txt',
            '+++ /dev/null',
            '@@ -1,2 +0,0 @@',
            '-line1',
            '-line2',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(1, $fileDiffs);
        self::assertSame('/dev/null', $fileDiffs[0]->targetPath);
        self::assertSame([], $fileDiffs[0]->addedLines);
    }

    public function testSectionsWithoutHunksProduceNoFileDiff(): void
    {
        $patch = self::linesToPatch([
            'diff --git a/renamed-only.txt b/renamed-new.txt', // rename without edits
            'similarity index 100%',
            'rename from renamed-only.txt',
            'rename to renamed-new.txt',
            'diff --git a/script.sh b/script.sh', // mode-only change
            'old mode 100644',
            'new mode 100755',
            'diff --git a/image.png b/image.png', // binary file
            'index 1111111..2222222 100644',
            'Binary files a/image.png and b/image.png differ',
            'diff --git a/kept.txt b/kept.txt',
            'index 5555555..6666666 100644',
            '--- a/kept.txt',
            '+++ b/kept.txt',
            '@@ -1 +1,2 @@',
            ' line1',
            '+line2',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(1, $fileDiffs);
        self::assertSame('b/kept.txt', $fileDiffs[0]->targetPath);
        self::assertSame([2 => 'line2'], $fileDiffs[0]->addedLines);
    }

    public function testRenameWithEditsUsesNewPath(): void
    {
        $patch = self::linesToPatch([
            'diff --git a/old-name.txt b/new-name.txt',
            'similarity index 90%',
            'rename from old-name.txt',
            'rename to new-name.txt',
            'index 1111111..2222222 100644',
            '--- a/old-name.txt',
            '+++ b/new-name.txt',
            '@@ -1,2 +1,2 @@',
            ' line1',
            '-line2',
            '+line2 changed',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(1, $fileDiffs);
        self::assertSame('b/new-name.txt', $fileDiffs[0]->targetPath);
        self::assertSame([2 => 'line2 changed'], $fileDiffs[0]->addedLines);
    }

    public function testNoNewlineAtEndOfFileMarkersAreIgnored(): void
    {
        $patch = self::linesToPatch([
            'diff --git a/file.txt b/file.txt',
            'index 1111111..2222222 100644',
            '--- a/file.txt',
            '+++ b/file.txt',
            '@@ -1,2 +1,2 @@',
            ' line1',
            '-line2',
            '\ No newline at end of file',
            '+line2 changed',
            '\ No newline at end of file',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(1, $fileDiffs);
        self::assertSame([2 => 'line2 changed'], $fileDiffs[0]->addedLines);
    }

    public function testZeroContextHunks(): void
    {
        $patch = self::linesToPatch([
            'diff --git a/file.txt b/file.txt',
            'index 1111111..2222222 100644',
            '--- a/file.txt',
            '+++ b/file.txt',
            '@@ -5,0 +6,2 @@ function context',
            '+line6',
            '+line7',
            '@@ -10,2 +12,0 @@',
            '-line10',
            '-line11',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(1, $fileDiffs);
        self::assertSame([6 => 'line6', 7 => 'line7'], $fileDiffs[0]->addedLines);
    }

    public function testCrlfPatchIsNormalized(): void
    {
        $patch = implode("\r\n", [
            'diff --git a/file.txt b/file.txt',
            'index 1111111..2222222 100644',
            '--- a/file.txt',
            '+++ b/file.txt',
            '@@ -1 +1,2 @@',
            ' line1',
            '+line2',
            '',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(1, $fileDiffs);
        self::assertSame([2 => 'line2'], $fileDiffs[0]->addedLines);
    }

    public function testEmptyContextLineWithStrippedSpaceIsTolerated(): void
    {
        $patch = self::linesToPatch([
            'diff --git a/file.txt b/file.txt',
            'index 1111111..2222222 100644',
            '--- a/file.txt',
            '+++ b/file.txt',
            '@@ -1,3 +1,4 @@',
            ' line1',
            '', // an empty context line whose single space got stripped
            '+line3',
            ' line4',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(1, $fileDiffs);
        self::assertSame([3 => 'line3'], $fileDiffs[0]->addedLines);
    }

    public function testPlainUnifiedDiffWithTimestamps(): void
    {
        $patch = self::linesToPatch([
            "--- a/file.txt\t2026-08-17 10:00:00.000000000 +0000",
            "+++ b/file.txt\t2026-08-17 10:00:01.000000000 +0000",
            '@@ -1 +1,2 @@',
            ' line1',
            '+line2',
        ]);

        $fileDiffs = (new UnifiedDiffParser())->parse($patch, 'changes.patch');

        self::assertCount(1, $fileDiffs);
        self::assertSame('b/file.txt', $fileDiffs[0]->targetPath);
        self::assertSame([2 => 'line2'], $fileDiffs[0]->addedLines);
    }

    public function testEmptyPatchProducesNoFileDiffs(): void
    {
        self::assertSame([], (new UnifiedDiffParser())->parse('', 'changes.patch'));
        self::assertSame([], (new UnifiedDiffParser())->parse("\n", 'changes.patch'));
    }

    /**
     * @param list<string> $patchLines
     */
    #[DataProvider('provideMalformedPatches')]
    public function testMalformedPatchIsRejected(
        array $patchLines,
        string $expectedMessagePart,
    ): void
    {
        $parser = new UnifiedDiffParser();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        $parser->parse(self::linesToPatch($patchLines), 'changes.patch');
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function provideMalformedPatches(): iterable
    {
        yield 'unexpected top-level line' => [
            ['some garbage'],
            "malformed at line #1: unexpected line 'some garbage'",
        ];

        yield 'unexpected extended header' => [
            [
                'diff --git a/file.txt b/file.txt',
                'something unexpected',
            ],
            "malformed at line #2: unexpected line 'something unexpected'",
        ];

        yield 'missing +++ header' => [
            [
                'diff --git a/file.txt b/file.txt',
                '--- a/file.txt',
                '@@ -1 +1 @@',
            ],
            "malformed at line #2: '---' header not followed by '+++' header",
        ];

        yield 'hunk without headers' => [
            [
                'diff --git a/file.txt b/file.txt',
                'old mode 100644',
                'new mode 100755',
                '@@ -1 +1,2 @@',
                ' line1',
                '+line2',
            ],
            "malformed at line #4: hunk without preceding '+++' header",
        ];

        yield 'declared counts exceed hunk body' => [
            [
                'diff --git a/file.txt b/file.txt',
                'index 1111111..2222222 100644',
                '--- a/file.txt',
                '+++ b/file.txt',
                '@@ -1,2 +1,3 @@',
                ' line1',
                '+line2',
            ],
            'malformed at line #8: patch ends in the middle of a hunk',
        ];

        yield 'hunk body exceeds declared counts' => [
            [
                'diff --git a/file.txt b/file.txt',
                'index 1111111..2222222 100644',
                '--- a/file.txt',
                '+++ b/file.txt',
                '@@ -1 +1,2 @@',
                ' line1',
                '+line2',
                '+line3',
            ],
            "malformed at line #8: unexpected line '+line3'",
        ];

        yield 'added line where counts allow only context' => [
            [
                'diff --git a/file.txt b/file.txt',
                'index 1111111..2222222 100644',
                '--- a/file.txt',
                '+++ b/file.txt',
                '@@ -1,2 +1,2 @@',
                ' line1',
                '+line2',
                ' line3',
            ],
            'malformed at line #8: hunk body does not match the line counts declared in its header',
        ];

        yield 'invalid hunk header' => [
            [
                'diff --git a/file.txt b/file.txt',
                'index 1111111..2222222 100644',
                '--- a/file.txt',
                '+++ b/file.txt',
                '@@ invalid @@',
            ],
            "malformed at line #5: invalid hunk header '@@ invalid @@'",
        ];

        yield 'quoted target path' => [
            [
                'diff --git "a/we ird.txt" "b/we ird.txt"',
                'index 1111111..2222222 100644',
                '--- "a/we ird.txt"',
                '+++ "b/we ird.txt"',
                '@@ -1 +1,2 @@',
                ' line1',
                '+line2',
            ],
            'malformed at line #4: quoted file paths are not supported',
        ];

        yield 'git binary patch' => [
            [
                'diff --git a/image.png b/image.png',
                'index 1111111..2222222 100644',
                'GIT binary patch',
                'literal 100',
            ],
            'malformed at line #3: binary patches are not supported',
        ];
    }

    /**
     * @param list<string> $lines
     */
    private static function linesToPatch(array $lines): string
    {
        return implode("\n", $lines) . "\n";
    }

}
