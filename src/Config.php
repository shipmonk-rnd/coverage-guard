<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Excluder\ExecutableLineExcluder;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Utils\FileUtils;
use function file_exists;
use function is_dir;
use const DIRECTORY_SEPARATOR;

/**
 * This class is expected to be returned from a config file passed via --config option.
 *
 * @api
 */
final class Config
{

    private ?string $gitRoot = null;

    /**
     * @var array<string, string>
     */
    private array $coveragePathMapping = [];

    /**
     * @var list<CoverageRule>
     */
    private array $rules = [];

    /**
     * @var list<ExecutableLineExcluder>
     */
    private array $excluders = [];

    private ?string $editorUrl = null;

    public function __construct()
    {
    }

    /**
     * Git root is autodetected by .git presence, but you can override it here if needed.
     *
     * @throws ErrorException
     */
    public function setGitRoot(string $gitRoot): self
    {
        if (!is_dir($gitRoot)) {
            throw new ErrorException("Provided git root '$gitRoot' is not a directory");
        }

        $this->gitRoot = FileUtils::realpath($gitRoot) . DIRECTORY_SEPARATOR;
        return $this;
    }

    /**
     * Allows you to remap paths in coverage files to existing directories on your filesystem.
     * Handy for CI-generated coverage reports that contain absolute paths in CI environment.
     *
     * @throws ErrorException
     */
    public function addCoveragePathMapping(
        string $originalPathInCoverageFile,
        string $existingPathToUseInstead,
    ): self
    {
        if (!file_exists($existingPathToUseInstead)) {
            throw new ErrorException("Provided new path '$existingPathToUseInstead' does not exist");
        }
        if (!is_dir($existingPathToUseInstead)) {
            throw new ErrorException("Provided new path '$existingPathToUseInstead' is not a directory");
        }

        $this->coveragePathMapping[$originalPathInCoverageFile] = $existingPathToUseInstead;
        return $this;
    }

    /**
     * Main entry point for your custom rules, see EnforceCoverageForMethodsRule for inspiration.
     */
    public function addRule(CoverageRule $rule): self
    {
        $this->rules[] = $rule;
        return $this;
    }

    /**
     * Allows you to ignore/exclude certain executable lines from coverage calculations.
     */
    public function addExecutableLineExcluder(ExecutableLineExcluder $excluder): self
    {
        $this->excluders[] = $excluder;
        return $this;
    }

    /**
     * Set the editor URL pattern to make filepaths clickable in CLI output via OSC 8 hyperlink
     *
     * Available placeholders:
     * - {file} - Absolute file path
     * - {relFile} - Relative file path (from current working directory)
     * - {line} - Line number
     *
     * Common editor URL patterns:
     * - PHPStorm: phpstorm://open?file={file}&line={line}
     * - VS Code: vscode://file/{file}:{line}
     * - Sublime: subl://open?url=file://{file}&line={line}
     */
    public function setEditorUrl(string $editorUrl): self
    {
        $this->editorUrl = $editorUrl;
        return $this;
    }

    public function getGitRoot(): ?string
    {
        return $this->gitRoot;
    }

    /**
     * @return array<string, string>
     */
    public function getCoveragePathMapping(): array
    {
        return $this->coveragePathMapping;
    }

    /**
     * @return list<ExecutableLineExcluder>
     */
    public function getExecutableLineExcluders(): array
    {
        return $this->excluders;
    }

    /**
     * @return list<CoverageRule>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function getEditorUrl(): ?string
    {
        return $this->editorUrl;
    }

}
