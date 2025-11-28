<?php declare(strict_types = 1);

class SimpleClass
{

    public function simpleMethod(): string
    {
        return 'test';
    }

    public function anotherMethod(int $value): int
    {
        if ($value > 0) {
            return $value * 2;
        }

        return 0;
    }

}
