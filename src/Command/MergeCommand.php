<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Coverage\CoverageMerger;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function count;
use function file_get_contents;
use function is_file;
use function str_contains;
use function str_ends_with;
use const STDOUT;

final class MergeCommand extends AbstractCommand
{

    public function getName(): string
    {
        return 'merge';
    }

    public function getDescription(): string
    {
        return 'Merge multiple coverage files into one';
    }

    public function getArguments(): array
    {
        return [
            new Argument('files', 'Coverage files to merge (clover.xml, cobertura.xml, or .cov)', variadic: true),
        ];
    }

    public function getOptions(): array
    {
        return [
            new Option('format', 'Output format: clover or cobertura (default: auto-detect from first file)', requiresValue: true),
        ];
    }

    /**
     * @throws ErrorException
     */
    protected function run(Printer $printer): int
    {
        $inputFiles = $this->getVariadicArgument('files');

        if (count($inputFiles) < 2) {
            throw new ErrorException('At least 2 files are required to merge');
        }

        // Validate all input files exist
        foreach ($inputFiles as $file) {
            if (!is_file($file)) {
                throw new ErrorException("File not found: {$file}");
            }
        }

        // Extract coverage from all files
        $coverageSets = [];
        foreach ($inputFiles as $file) {
            $extractor = $this->createExtractor($file);
            $coverageSets[] = $extractor->getCoverage($file);
        }

        // Merge coverage data
        $merged = CoverageMerger::merge($coverageSets);

        // Determine output format
        $format = $this->getEnumOption('format', CoverageFormat::class);
        if ($format === null) {
            $format = $this->detectFormat($inputFiles[0]);
        }

        // Write merged coverage to stdout
        $writer = $this->createWriter($format);
        $writer->write($merged, STDOUT);

        return 0;
    }

    /**
     * @throws ErrorException
     */
    private function detectFormat(string $file): CoverageFormat
    {
        if (str_ends_with($file, '.cov')) {
            return CoverageFormat::Clover; // .cov files are typically converted to clover
        }

        $content = file_get_contents($file);

        if ($content === false) {
            throw new ErrorException("Failed to read file: {$file}");
        }

        if (str_contains($content, 'cobertura')) {
            return CoverageFormat::Cobertura;
        }

        return CoverageFormat::Clover;
    }

}
