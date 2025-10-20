<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function is_file;
use const STDOUT;

final class ConvertCommand extends AbstractCommand
{

    public function getName(): string
    {
        return 'convert';
    }

    public function getDescription(): string
    {
        return 'Convert coverage file between different formats';
    }

    public function getArguments(): array
    {
        return [
            new Argument('input-file', 'Input coverage file (clover.xml, cobertura.xml, or .cov)'),
        ];
    }

    public function getOptions(): array
    {
        return [
            new Option('format', 'Output format: clover or cobertura (required)', requiresValue: true),
        ];
    }

    /**
     * @throws ErrorException
     */
    protected function run(Printer $printer): int
    {
        $inputFile = $this->getArgument('input-file');
        $format = $this->getEnumOption('format', CoverageFormat::class);

        if (!is_file($inputFile)) {
            throw new ErrorException("File not found: {$inputFile}");
        }

        if ($format === null) {
            throw new ErrorException('Option --format is required. Use "clover" or "cobertura"');
        }

        // Extract coverage from input file
        $extractor = $this->createExtractor($inputFile);
        $coverage = $extractor->getCoverage($inputFile);

        // Write to stdout in the desired format
        $writer = $this->createWriter($format);
        $writer->write($coverage, STDOUT);

        return 0;
    }

}
