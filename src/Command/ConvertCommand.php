<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\CliArgument;
use ShipMonk\CoverageGuard\Cli\CliOption;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Writer\CoverageWriterFactory;
use function fwrite;
use const STDOUT;

final class ConvertCommand implements Command
{

    /**
     * @param resource $outputStream
     */
    public function __construct(
        private readonly CoverageProvider $coverageProvider,
        private readonly ConfigResolver $configResolver,
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

        #[CliOption(name: 'config', description: 'Path to PHP config file')]
        ?string $configPath = null,

        #[CliOption(description: 'XML indent to use')]
        string $indent = '    ',
    ): int
    {
        $config = $this->configResolver->resolveConfig($configPath);
        $coverage = $this->coverageProvider->getCoverage($config, $inputFile);

        $writer = CoverageWriterFactory::create($format);
        $xml = $writer->write($coverage, $indent);

        fwrite($this->outputStream, $xml);

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
