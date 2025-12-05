<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Fixtures;

use LogicException;

final class MyLogicException extends LogicException {}

class ClassWithThrowStatements
{

    public function methodWithSingleLineThrow(): void
    {
        throw new MyLogicException('error'); // excluded
    }

    public function methodWithMultilineThrow(): void
    {
        throw new MyLogicException( // excluded
            'multi-line error message' // excluded
        ); // excluded
    }

    public function methodWithThrowVariable(): void
    {
        $exception = new MyLogicException('error');
        throw $exception;
    }

    public function methodWithNonMatchingException(): void
    {
        throw new LogicException('different exception');
    }

}
