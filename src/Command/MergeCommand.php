<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\CliArgument;
use ShipMonk\CoverageGuard\Cli\CliOption;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Coverage\CoverageMerger;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Writer\CoverageWriterFactory;
use function count;
use function fwrite;
use const STDOUT;

final class MergeCommand extends AbstractCommand
{

    /**
     * @param resource $outputStream
     */
    public function __construct(
        private readonly CoverageProvider $extractorFactory,
        private readonly CoverageMerger $coverageMerger,
        private readonly ConfigResolver $configResolver,
        private readonly mixed $outputStream = STDOUT,
    )
    {
    }

    /**
     * @throws ErrorException
     */
    public function __invoke(
        #[CliOption(description: 'Output format, use clover|cobertura')]
        CoverageFormat $format = CoverageFormat::Clover,

        #[CliOption(description: 'XML indent to use')]
        string $indent = '    ',

        #[CliOption(name: 'config', description: 'Path to PHP config file')]
        ?string $configPath = null,

        #[CliArgument(description: 'Coverage files to merge (clover.xml, cobertura.xml, or .cov)')]
        string ...$files,
    ): int
    {
        if (count($files) < 2) {
            throw new ErrorException('At least 2 files are required to merge');
        }

        $config = $this->configResolver->resolveConfig($configPath);

        $coverageSets = [];
        foreach ($files as $file) {
            $coverageSets[] = $this->extractorFactory->getCoverage($config, $file);
        }

        $merged = $this->coverageMerger->merge($coverageSets);
        $xml = CoverageWriterFactory::create($format)->write($merged, $indent);

        fwrite($this->outputStream, $xml);

        return 0;
    }

    public function getName(): string
    {
        return 'merge';
    }

    public function getDescription(): string
    {
        return 'Merge multiple coverage files into one';
    }

}
