<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Fixtures;

use LogicException;
use RuntimeException;

class ClassWithThrowStatements
{

    public function methodWithSingleLineThrow(): void
    {
        throw new RuntimeException('error'); // excluded
    }

    public function methodWithMultilineThrow(): void
    {
        throw new RuntimeException( // excluded
            'multi-line error message' // excluded
        ); // excluded
    }

    public function methodWithThrowVariable(): void
    {
        $exception = new RuntimeException('error');
        throw $exception;
    }

    public function methodWithNonMatchingException(): void
    {
        throw new LogicException('different exception');
    }

}
