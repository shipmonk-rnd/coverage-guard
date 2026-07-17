<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Fixtures;

use LogicException;

class ClassWithThrow
{

    public function doSomething(bool $flag): int
    {
        if ($flag) {
            throw new LogicException('boom');
        }

        return 42;
    }

}
