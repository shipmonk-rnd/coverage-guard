<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use Composer\InstalledVersions;
use LogicException;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser as DiffParser;
use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function exec;
use function file_get_contents;
use function function_exists;
use function is_dir;
use function is_file;
use function method_exists;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

final class PatchParser
{

    public function __construct(
        private readonly string $cwd,
        private readonly Printer $printer,
    )
    {
    }

    /**
     * @return array<string, list<int>> file => list of changed line numbers
     *
     * @throws ErrorException
     */
    public function getPatchChangedLines(
        string $patchFile,
        Config $config,
    ): array
    {
        if (!is_file($patchFile)) {
            throw new ErrorException("Patch file not found: {$patchFile}");
        }

        if (!str_ends_with($patchFile, '.patch') && !str_ends_with($patchFile, '.diff')) {
            throw new ErrorException("Unknown patch filepath {$patchFile}, expecting .patch or .diff extension");
        }

        if (!InstalledVersions::isInstalled('sebastian/diff')) {
            throw new ErrorException('In order to use --patch mode, you need to install sebastian/diff');
        }

        $gitRoot = $this->resolveGitRoot($config);
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
                throw new ErrorException("Patch file '{$patchFile}' uses unsupported prefix in '{$diffTo}'. Only standard 'b/' is supported. Please use 'git diff --dst-prefix=b/' to regenerate the patch file.");
            }
            $absolutePath = $gitRoot . substr($diffTo, 2);

            if (!is_file($absolutePath)) {
                throw new ErrorException("File '{$absolutePath}' present in patch file '{$patchFile}' was not found. Is the patch up-to-date?");
            }

            $realPath = FileUtils::realpath($absolutePath);
            $actualFileLines = FileUtils::readFileLines($realPath);

            $changes[$realPath] = [];

            $diffChunks = method_exists($diff, 'chunks') ? $diff->chunks() : $diff->getChunks();
            foreach ($diffChunks as $chunk) {
                $lineNumber = method_exists($chunk, 'end') ? $chunk->end() : $chunk->getEnd();
                $chunkLines = method_exists($chunk, 'lines') ? $chunk->lines() : $chunk->getLines();

                foreach ($chunkLines as $line) {
                    $lineType = method_exists($line, 'type') ? $line->type() : $line->getType();
                    $lineContent = method_exists($line, 'content') ? $line->content() : $line->getContent();

                    if ($lineType === Line::ADDED) {
                        if (!isset($actualFileLines[$lineNumber - 1])) {
                            throw new ErrorException("Patch file '{$patchFile}' refers to added line #{$lineNumber} with '{$lineContent}' contents in file '{$realPath}', but such line does not exist. Is the patch up-to-date?");
                        }

                        $actualLine = $actualFileLines[$lineNumber - 1];

                        if ($lineContent !== $actualLine) {
                            throw new ErrorException("Patch file '{$patchFile}' has added line #{$lineNumber} that does not match actual content of file '{$realPath}'.\nPatch data: '{$lineContent}'\nFilesystem: '{$actualLine}'\n\nIs the patch up-to-date?");
                        }
                    }

                    if ($lineType === Line::ADDED) {
                        $changes[$realPath][] = $lineNumber;
                    }

                    if ($lineType !== Line::REMOVED) {
                        $lineNumber++;
                    }
                }
            }
        }

        if ($diffs === []) {
            $this->printer->printWarning("Patch file '{$patchFile}' does not contain any changes. Is it valid patch file?");
        }

        return $changes;
    }

    private function detectGitRoot(): ?string
    {
        if (is_dir($this->cwd . '/.git/')) {
            return $this->cwd;
        }

        if (!function_exists('exec')) {
            return null;
        }

        $output = [];
        $returnCode = 0;

        @exec('git rev-parse --show-toplevel 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0 && isset($output[0]) && strlen($output[0]) !== 0) {
            $gitRoot = trim($output[0]);
            if (is_dir($gitRoot)) {
                return $gitRoot;
            }
        }

        return null;
    }

    /**
     * @throws ErrorException
     */
    private function resolveGitRoot(Config $config): string
    {
        $gitRoot = $config->getGitRoot();

        // Auto-detect git root if not provided
        if ($gitRoot === null) {
            $detected = $this->detectGitRoot();
            if ($detected === null) {
                throw new ErrorException('In order to process patch files, you need to be inside git repository folder, install git or specify git root');
            }
            $config->setGitRoot($detected);
            // Get the normalized path with separator from config
            $gitRoot = $config->getGitRoot();
            if ($gitRoot === null) {
                throw new LogicException('We just set non-nullable value, get/set broken');
            }
            return $gitRoot;
        }

        return $gitRoot;
    }

}
