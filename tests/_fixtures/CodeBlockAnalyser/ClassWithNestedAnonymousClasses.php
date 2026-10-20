<?php declare(strict_types = 1);

class ClassWithNestedAnonymousClasses
{

    public function methodWithAnonymousClass(): object
    {
        return new class {
            public function methodWithInnerAnonymousClass(): object
            {
                return new class {
                };
            }

            public function methodAfterInnerAnonymousClass(): void
            {
                echo 'Hi';
            }
        };
    }

    public function regularMethod(): void
    {
        echo 'Hello';
    }

}
