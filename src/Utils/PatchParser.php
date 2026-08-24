<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function dirname;
use function file_exists;
use function file_get_contents;
use function is_file;
use function str_ends_with;
use function str_starts_with;
use function substr;
use const DIRECTORY_SEPARATOR;

final class PatchParser
{

    private readonly UnifiedDiffParser $unifiedDiffParser;

    public function __construct(
        private readonly string $cwd,
        private readonly Printer $printer,
    )
    {
        $this->unifiedDiffParser = new UnifiedDiffParser();
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

        $gitRoot = $this->resolveGitRoot($config);
        $patchContent = file_get_contents($patchFile);

        if ($patchContent === false) {
            throw new ErrorException("Failed to read patch file: {$patchFile}");
        }

        $fileDiffs = $this->unifiedDiffParser->parse($patchContent, $patchFile);
        $changes = [];

        foreach ($fileDiffs as $fileDiff) {
            $diffTo = $fileDiff->targetPath;
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

            foreach ($fileDiff->addedLines as $lineNumber => $lineContent) {
                if (!isset($actualFileLines[$lineNumber - 1])) {
                    throw new ErrorException("Patch file '{$patchFile}' refers to added line #{$lineNumber} with '{$lineContent}' contents in file '{$realPath}', but such line does not exist. Is the patch up-to-date?");
                }

                $actualLine = $actualFileLines[$lineNumber - 1];

                if ($lineContent !== $actualLine) {
                    throw new ErrorException("Patch file '{$patchFile}' has added line #{$lineNumber} that does not match actual content of file '{$realPath}'.\nPatch data: '{$lineContent}'\nFilesystem: '{$actualLine}'\n\nIs the patch up-to-date?");
                }

                $changes[$realPath][] = $lineNumber;
            }
        }

        if ($fileDiffs === []) {
            $this->printer->printWarning("Patch file '{$patchFile}' does not contain any changes. Is it valid patch file?");
        }

        return $changes;
    }

    private function detectGitRoot(): ?string
    {
        $dir = $this->cwd;

        while (true) {
            if (file_exists($dir . '/.git')) {
                return $dir;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                // Reached filesystem root
                return null;
            }

            $dir = $parent;
        }
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

            return FileUtils::realpath($detected) . DIRECTORY_SEPARATOR;
        }

        return $gitRoot;
    }

}
