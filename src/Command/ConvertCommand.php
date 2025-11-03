<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\CliArgument;
use ShipMonk\CoverageGuard\Cli\CliOption;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function fwrite;
use function is_file;
use const STDOUT;

final class ConvertCommand extends AbstractCommand
{

    /**
     * @param resource $outputStream
     */
    public function __construct(
        private readonly mixed $outputStream = STDOUT,
    )
    {
    }

    /**
     * @throws ErrorException
     */
    public function __invoke(
        #[CliArgument('input-file', description: 'Input coverage file (clover.xml, cobertura.xml, or .cov)')]
        string $inputFile,

        #[CliOption(description: 'Output format: clover or cobertura')]
        CoverageFormat $format,

        #[CliOption(description: 'XML indent to use')]
        string $indent = '    ',
    ): int
    {
        if (!is_file($inputFile)) {
            throw new ErrorException("File not found: {$inputFile}");
        }

        $extractor = $this->createExtractor($inputFile);
        $coverage = $extractor->getCoverage($inputFile);

        $writer = $this->createWriter($format);
        $xml = $writer->write($coverage);

        // TODO should be inside writers
        $normalizedXml = $this->convertIndentation($xml, '  ', $indent);

        fwrite($this->outputStream, $normalizedXml);

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
