<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use BackedEnum;
use Composer\InstalledVersions;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser as DiffParser;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoberturaCoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\CoverageExtractor;
use ShipMonk\CoverageGuard\Extractor\PhpUnitCoverageExtractor;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Writer\CloverCoverageWriter;
use ShipMonk\CoverageGuard\Writer\CoberturaCoverageWriter;
use ShipMonk\CoverageGuard\Writer\CoverageWriter;
use ShipMonk\CoverageGuard\XmlLoader;
use function array_map;
use function array_slice;
use function exec;
use function file_get_contents;
use function function_exists;
use function implode;
use function is_dir;
use function is_string;
use function method_exists;
use function realpath;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

abstract class AbstractCommand implements Command
{

    /**
     * @var list<string>
     */
    private array $arguments = [];

    /**
     * @var array<string, string|bool>
     */
    private array $options = [];

    /**
     * @param list<string> $arguments
     * @param array<string, string|bool> $options
     */
    final public function execute(
        array $arguments,
        array $options,
        Printer $printer,
    ): int
    {
        $this->arguments = $arguments;
        $this->options = $options;

        return $this->run($printer);
    }

    /**
     * Implement this method instead of execute()
     */
    abstract protected function run(Printer $printer): int;

    /**
     * Get a positional argument by name
     *
     * @throws ErrorException If argument is not defined or not provided
     */
    protected function getArgument(string $name): string
    {
        $arguments = $this->getArguments();

        foreach ($arguments as $index => $argument) {
            if ($argument->name === $name) {
                if (!isset($this->arguments[$index])) {
                    throw new ErrorException("Required argument '{$name}' not provided");
                }

                return $this->arguments[$index];
            }
        }

        throw new ErrorException("Undefined argument: {$name}");
    }

    /**
     * Get all positional arguments as an array
     *
     * Useful for variadic arguments
     *
     * @param string $name Argument name (must be variadic)
     * @return list<string>
     *
     * @throws ErrorException If argument is not variadic
     */
    protected function getVariadicArgument(string $name): array
    {
        $arguments = $this->getArguments();

        foreach ($arguments as $index => $argument) {
            if ($argument->name === $name) {
                if (!$argument->variadic) {
                    throw new ErrorException("Argument '{$name}' is not variadic");
                }

                return array_slice($this->arguments, $index);
            }
        }

        throw new ErrorException("Undefined argument: {$name}");
    }

    /**
     * Get an option value
     *
     * @return string|bool|null String for options with values, bool for flags, null if not provided
     *
     * @throws ErrorException If option is not defined
     */
    protected function getOption(string $name): string|bool|null
    {
        $options = $this->getOptions();

        foreach ($options as $option) {
            if ($option->name === $name) {
                return $this->options[$name] ?? null;
            }
        }

        throw new ErrorException("Undefined option: --{$name}");
    }

    /**
     * Get a required option value as a string
     *
     * @return string String value
     *
     * @throws ErrorException If option is not provided, not defined, or is a boolean flag
     */
    protected function getRequiredStringOption(string $name): string
    {
        $value = $this->getOption($name);

        if ($value === null || $value === '') {
            throw new ErrorException("Option --{$name} is required");
        }

        if ($value === true || $value === false) {
            throw new ErrorException("Option --{$name} is a boolean flag, not a string option");
        }

        return $value;
    }

    /**
     * Get an option value as a boolean
     *
     * @return bool True if flag was provided, false otherwise
     *
     * @throws ErrorException If option is not defined or requires a value
     */
    protected function getBoolOption(string $name): bool
    {
        $value = $this->getOption($name);

        if (is_string($value)) {
            throw new ErrorException("Option --{$name} requires a value, cannot be used as a boolean flag");
        }

        return $value === true;
    }

    /**
     * Get an option value as an enum
     *
     * @param class-string<T> $enumClass
     * @return T|null
     *
     * @template T of BackedEnum
     *
     * @throws ErrorException
     */
    protected function getEnumOption(
        string $name,
        string $enumClass,
    ): ?BackedEnum
    {
        $value = $this->getOption($name);

        if ($value === null || $value === '') {
            return null;
        }

        if ($value === true || $value === false) {
            throw new ErrorException("Option --{$name} requires a value");
        }

        foreach ($enumClass::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        $validValues = array_map(static fn (BackedEnum $case) => $case->value, $enumClass::cases());
        $validValuesStr = implode(', ', $validValues);

        throw new ErrorException("Invalid value '{$value}' for option --{$name}. Expected one of: {$validValuesStr}");
    }

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
