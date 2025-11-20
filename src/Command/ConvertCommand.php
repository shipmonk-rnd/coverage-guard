<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\Arguments\CoverageFileCliArgument;
use ShipMonk\CoverageGuard\Cli\CoverageOutputFormat;
use ShipMonk\CoverageGuard\Cli\Options\ConfigCliOption;
use ShipMonk\CoverageGuard\Cli\Options\IndentCliOption;
use ShipMonk\CoverageGuard\Cli\Options\OutputFormatCliOption;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Writer\CoverageWriterFactory;

final class ConvertCommand implements Command
{

    public function __construct(
        private readonly CoverageProvider $coverageProvider,
        private readonly ConfigResolver $configResolver,
        private readonly Printer $stdOutPrinter,
    )
    {
    }

    /**
     * @throws ErrorException
     */
    public function __invoke(
        #[CoverageFileCliArgument]
        string $inputFile,

        #[OutputFormatCliOption]
        CoverageOutputFormat $format,

        #[ConfigCliOption]
        ?string $configPath = null,

        #[IndentCliOption]
        string $indent = '    ',
    ): int
    {
        $config = $this->configResolver->resolveConfig($configPath);
        $coverage = $this->coverageProvider->getCoverage($config, $inputFile);

        $writer = CoverageWriterFactory::create($format);
        $xml = $writer->write($coverage, $indent);

        $this->stdOutPrinter->print($xml);

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
