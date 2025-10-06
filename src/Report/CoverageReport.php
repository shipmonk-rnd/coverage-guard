<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Report;

final class CoverageReport
{

    /**
     * @param list<ReportedError> $reportedErrors
     * @param list<string> $analysedFiles
     */
    public function __construct(
        public readonly array $reportedErrors,
        public readonly array $analysedFiles,
        public readonly bool $patchMode,
    )
    {
    }

}
