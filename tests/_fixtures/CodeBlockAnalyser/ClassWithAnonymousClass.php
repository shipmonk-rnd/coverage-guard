<?php declare(strict_types = 1);

class ClassWithAnonymousClass
{

    public function methodWithAnonymousClass(): void
    {
        new class {
            public function methodOfAnonymousClass(): void
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
