<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\CliArgument;
use ShipMonk\CoverageGuard\Cli\CliOption;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Coverage\CoverageMerger;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\ExtractorFactory;
use function count;
use function fwrite;
use function is_file;
use const STDOUT;

final class MergeCommand extends AbstractCommand
{

    /**
     * @param resource $outputStream
     */
    public function __construct(
        private readonly ExtractorFactory $extractorFactory,
        private readonly CoverageMerger $coverageMerger,
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

        #[CliArgument(description: 'Coverage files to merge (clover.xml, cobertura.xml, or .cov)')]
        string ...$files,
    ): int
    {
        if (count($files) < 2) {
            throw new ErrorException('At least 2 files are required to merge');
        }

        $coverageSets = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                throw new ErrorException("Given file not found: {$file}");
            }

            $extractor = $this->extractorFactory->createExtractor($file);
            $coverageSets[] = $extractor->getCoverage($file);
        }

        $merged = $this->coverageMerger->merge($coverageSets);
        $xml = $this->createWriter($format)->write($merged, $indent);

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
