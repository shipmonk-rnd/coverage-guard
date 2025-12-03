<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Fixtures;

class ClassWithMultilineCalls
{

    public function methodWithMultilineMethodCall(): void
    {
        $this->someMethod(
            'arg1', // excluded
            'arg2' // excluded
        ); // excluded
    }

    public function methodWithSingleLineMethodCall(): void
    {
        $this->someMethod('arg1', 'arg2');
    }

    public function methodWithMultilineStaticCall(): void
    {
        self::staticMethod(
            'arg1', // excluded
            'arg2', // excluded
            'arg3' // excluded
        ); // excluded
    }

    public function methodWithMultilineFunctionCall(): void
    {
        strlen(
            'test' // excluded
        ); // excluded
    }

    public function methodWithMultilineNew(): void
    {
        new \stdClass(
            'arg1', // excluded
            'arg2', // excluded
            'arg3', // excluded
            'arg4' // excluded
        ); // excluded
    }

    private function someMethod(string ...$args): void
    {
        function () use ($args) {
            echo 'a';
        };
    }

    private static function staticMethod(string ...$args): void
    {
        (function () {
            echo 'a'; // excluded
        })(); // excluded
    }

}
