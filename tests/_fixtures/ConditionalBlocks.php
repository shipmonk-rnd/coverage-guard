<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Fixtures;

class ConditionalBlocks
{

    public function testForeach(array $items): int
    {
        $sum = 0;
        foreach ($items as $item) {
            $sum += $item;
        }
        return $sum;
    }

    public function testFor(): int
    {
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += $i;
        }
        return $sum;
    }

    public function testWhile(): int
    {
        $i = 0;
        $sum = 0;
        while ($i < 10) {
            $sum += $i;
            $i++;
        }
        return $sum;
    }

    public function testDoWhile(): int
    {
        $i = 0;
        $sum = 0;
        do {
            $sum += $i;
            $i++;
        } while ($i < 10);
        return $sum;
    }

    public function testIf(int $value): string
    {
        if ($value > 0) {
            return 'positive';
        } elseif ($value < 0) {
            return 'negative';
        } else {
            return 'zero';
        }
    }

    public function testSwitch(int $value): string
    {
        switch ($value) {
            case 1:
                return 'one';
            case 2:
                return 'two';
            default:
                return 'other';
        }
    }

    public function testTryCatch(): mixed
    {
        try {
            return $this->riskyOperation();
        } catch (\Exception $e) {
            return 'error';
        } finally {
            // cleanup
            $this->cleanup();
        }
    }

    public function testClosure(): callable
    {
        return function (int $x): int {
            return $x * 2;
        };
    }

    public function testArrowFunction(): callable
    {
        return fn(int $x): int => $x * 2;
    }

    public function testMatch(int $value): string
    {
        return match ($value) {
            1 => 'one',
            2 => 'two',
            default => 'other',
        };
    }

    private function riskyOperation(): mixed
    {
        return 'success';
    }

    private function cleanup(): void
    {
    }

}

function standaloneFunction(): string
{
    return 'standalone';
}
