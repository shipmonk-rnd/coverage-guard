<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

interface Command
{

    public function getName(): string;

    public function getDescription(): string;

}
