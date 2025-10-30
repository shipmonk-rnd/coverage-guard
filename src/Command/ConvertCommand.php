<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use function is_file;
use const STDOUT;

final class ConvertCommand extends AbstractCommand
{

    /**
     * @throws ErrorException
     */
    public function __invoke(
        #[CliArgument('input-file', description: 'Input coverage file (clover.xml, cobertura.xml, or .cov)')]
        string $inputFile,

        #[CliOption(description: 'Output format: clover or cobertura (required)')]
        CoverageFormat $format,
    ): int
    {
        if (!is_file($inputFile)) {
            throw new ErrorException("File not found: {$inputFile}");
        }

        // Extract coverage from input file
        $extractor = $this->createExtractor($inputFile);
        $coverage = $extractor->getCoverage($inputFile);

        // Write to stdout in the desired format
        $writer = $this->createWriter($format);
        $writer->write($coverage, STDOUT);

        return 0;
    }

    public function getName(): string
    {
        return 'convert';
    }

    public function getDescription(): string
    {
        return 'Convert coverage file between different formats';
    }

}
