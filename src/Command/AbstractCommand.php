<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use Composer\InstalledVersions;
use LogicException;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser as DiffParser;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoberturaCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\PhpUnitCoverageExtractor;
use ShipMonk\CoverageGuard\Writer\CloverCoverageWriter;
use ShipMonk\CoverageGuard\Writer\CoberturaCoverageWriter;
use ShipMonk\CoverageGuard\Writer\CoverageWriter;
use ShipMonk\CoverageGuard\XmlLoader;
use function exec;
use function file_get_contents;
use function function_exists;
use function is_dir;
use function method_exists;
use function preg_replace_callback;
use function realpath;
use function round;
use function str_contains;
use function str_ends_with;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

abstract class AbstractCommand implements Command
{

    /**
     * Create coverage extractor based on file extension and content
     *
     * @throws ErrorException
     */
    protected function createExtractor(string $coverageFile): CoverageExtractor
    {
        if (str_ends_with($coverageFile, '.cov')) {
            return new PhpUnitCoverageExtractor();
        }

        if (str_ends_with($coverageFile, '.xml')) {
            return $this->detectExtractorForXml($coverageFile);
        }

        throw new ErrorException("Unknown coverage file format: '{$coverageFile}'. Expecting .cov or .xml");
    }

    /**
     * @throws ErrorException
     */
    private function detectExtractorForXml(string $xmlFile): CoverageExtractor
    {
        $xmlLoader = new XmlLoader();
        $content = file_get_contents($xmlFile);

        if ($content === false) {
            throw new ErrorException("Failed to read file: {$xmlFile}");
        }

        if (str_contains($content, 'cobertura')) {
            return new CoberturaCoverageExtractor($xmlLoader);
        }

        return new CloverCoverageExtractor($xmlLoader);
    }

    /**
     * Create coverage writer for the specified format
     */
    protected function createWriter(CoverageFormat $format): CoverageWriter
    {
        return match ($format) {
            CoverageFormat::Clover => new CloverCoverageWriter(),
            CoverageFormat::Cobertura => new CoberturaCoverageWriter(),
        };
    }

    protected function convertIndentation(
        string $in,
        string $from,
        string $to,
    ): string
    {
        $out = preg_replace_callback(
            pattern: '/^( +)/m',
            callback: static function (array $matches) use ($from, $to): string {
                $currentIndentLength = strlen($matches[1]);
                $level = (int) round($currentIndentLength / strlen($from));
                return str_repeat($to, $level);
            },
            subject: $in,
        );
        if ($out === null) {
            throw new LogicException('Failed to convert indentation');
        }
        return $out;
    }

    /**
     * Detect git repository root directory
     */
    protected function detectGitRoot(string $cwd): ?string
    {
        if (is_dir($cwd . '/.git/')) {
            return $cwd;
        }

        if (!function_exists('exec')) {
            return null;
        }

        $output = [];
        $returnCode = 0;

        @exec('git rev-parse --show-toplevel 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0 && isset($output[0]) && strlen($output[0]) !== 0) {
            $gitRoot = trim($output[0]);
            // Validate the returned path is actually a directory
            if (is_dir($gitRoot)) {
                return $gitRoot;
            }
        }

        return null;
    }

    /**
     * Parse patch file and extract changed line numbers per file
     *
     * @return array<string, list<int>>
     *
     * @throws ErrorException
     */
    protected function getPatchChangedLines(
        string $patchFile,
        string $gitRoot,
    ): array
    {
        if (!InstalledVersions::isInstalled('sebastian/diff')) {
            throw new ErrorException('In order to use --patch mode, you need to install sebastian/diff');
        }

        $patchContent = file_get_contents($patchFile);

        if ($patchContent === false) {
            throw new ErrorException("Failed to read patch file: {$patchFile}");
        }

        $diffs = (new DiffParser())->parse($patchContent);
        $changes = [];

        foreach ($diffs as $diff) {
            $diffTo = method_exists($diff, 'to') ? $diff->to() : $diff->getTo();
            if ($diffTo === '/dev/null') {
                continue; // deleted file
            }
            if (!str_starts_with($diffTo, 'b/')) {
                throw new ErrorException("Patch file '{$patchFile}' uses unsupported prefix in '{$diffTo}'. Only standard 'b/' is supported.");
            }
            $absolutePath = $gitRoot . substr($diffTo, 2);
            $realPath = $this->tryRealpath($absolutePath);

            if ($realPath === null) {
                continue; // File not found, skip it
            }

            $changes[$realPath] = [];

            $diffChunks = method_exists($diff, 'chunks') ? $diff->chunks() : $diff->getChunks();
            foreach ($diffChunks as $chunk) {
                $lineNumber = method_exists($chunk, 'end') ? $chunk->end() : $chunk->getEnd();
                $chunkLines = method_exists($chunk, 'lines') ? $chunk->lines() : $chunk->getLines();

                foreach ($chunkLines as $line) {
                    $lineType = method_exists($line, 'type') ? $line->type() : $line->getType();

                    if ($lineType === Line::ADDED) {
                        $changes[$realPath][] = $lineNumber;
                    }

                    if ($lineType !== Line::REMOVED) {
                        $lineNumber++;
                    }
                }
            }
        }

        return $changes;
    }

    /**
     * Get realpath or null if file doesn't exist
     */
    protected function tryRealpath(string $path): ?string
    {
        $realpath = realpath($path);
        return $realpath === false ? null : $realpath;
    }

}
