<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\Arguments\CoverageFileCliArgument;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Cli\Options\ConfigCliOption;
use ShipMonk\CoverageGuard\Cli\Options\IndentCliOption;
use ShipMonk\CoverageGuard\Cli\Options\OutputFormatCliOption;
use ShipMonk\CoverageGuard\Coverage\CoverageMerger;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use ShipMonk\CoverageGuard\Writer\CoverageWriterFactory;
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
        CoverageFormat $format = CoverageFormat::Clover,

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

        $coverageSets = [];
        foreach ($files as $file) {
            $coverageSets[] = $this->coverageProvider->getCoverage($config, $file);
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
