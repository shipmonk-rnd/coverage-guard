<?php declare(strict_types = 1);

class ClassWithClassDeclaredInMethod
{

    public function methodDeclaringClass(): void
    {
        class ClassDeclaredInMethod
        {
            public function innerMethod(): void
            {
                echo 'Hi';
            }
        }
    }

    public function regularMethod(): void
    {
        echo 'Hello';
    }

}
