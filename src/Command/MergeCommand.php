<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\Arguments\CoverageFileCliArgument;
use ShipMonk\CoverageGuard\Cli\CoverageInputFormat;
use ShipMonk\CoverageGuard\Cli\CoverageOutputFormat;
use ShipMonk\CoverageGuard\Cli\Options\ConfigCliOption;
use ShipMonk\CoverageGuard\Cli\Options\IndentCliOption;
use ShipMonk\CoverageGuard\Cli\Options\OutputFormatCliOption;
use ShipMonk\CoverageGuard\Coverage\CoverageFormatDetector;
use ShipMonk\CoverageGuard\Coverage\CoverageMerger;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Writer\CoverageWriterFactory;
use function array_map;
use function array_unique;
use function count;
use function fwrite;
use const STDOUT;

final class MergeCommand implements Command
{

    /**
     * @param resource $outputStream
     */
    public function __construct(
        private readonly CoverageProvider $coverageProvider,
        private readonly CoverageMerger $coverageMerger,
        private readonly CoverageFormatDetector $coverageFormatDetector,
        private readonly ConfigResolver $configResolver,
        private readonly mixed $outputStream = STDOUT,
    )
    {
    }

    /**
     * @throws ErrorException
     */
    public function __invoke(
        #[OutputFormatCliOption]
        ?CoverageOutputFormat $format = null,

        #[IndentCliOption]
        string $indent = '    ',

        #[ConfigCliOption]
        ?string $configPath = null,

        #[CoverageFileCliArgument]
        string ...$files,
    ): int
    {
        if (count($files) < 2) {
            throw new ErrorException('At least 2 files are required to merge');
        }

        $config = $this->configResolver->resolveConfig($configPath);

        $inputFormats = [];
        $coverageSets = [];
        foreach ($files as $file) {
            $inputFormats[] = $this->coverageFormatDetector->detectFormat($file);
            $coverageSets[] = $this->coverageProvider->getCoverage($config, $file);
        }

        if ($format === null) {
            $format = $this->autodetectOutputFormat($inputFormats);
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

    /**
     * @param non-empty-list<CoverageInputFormat> $formats
     *
     * @throws ErrorException
     */
    private function autodetectOutputFormat(array $formats): CoverageOutputFormat
    {
        $uniqueFormats = array_unique(array_map(static fn (CoverageInputFormat $format): string => $format->value, $formats));

        if (count($uniqueFormats) > 1) {
            throw new ErrorException('Merging coverage files of different formats requires --output-format option to be specified');
        }

        $format = $formats[0];

        return match ($format) {
            CoverageInputFormat::Clover => CoverageOutputFormat::Clover,
            CoverageInputFormat::Cobertura => CoverageOutputFormat::Cobertura,
            CoverageInputFormat::Php => throw new ErrorException('Merging PHP coverage formats requires --output-format option to be specified'),
        };
    }

}
