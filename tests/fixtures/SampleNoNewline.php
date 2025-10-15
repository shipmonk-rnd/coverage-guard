<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Fixtures;

class SampleNoNewline
{

    public function testedMethod(): int
    {
        return 42;
    }

    public function untestedMethod(): string
    {
        return 'hello';
    }

}