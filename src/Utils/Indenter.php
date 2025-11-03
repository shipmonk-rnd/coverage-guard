<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use LogicException;
use function floor;
use function preg_replace_callback;
use function str_repeat;
use function strlen;

final class Indenter
{

    public static function change(
        string $code,
        string $from,
        string $to,
    ): string
    {
        $out = preg_replace_callback(
            pattern: '/^( +)/m',
            callback: static function (array $matches) use ($from, $to): string {
                $currentIndentLength = strlen($matches[1]);
                $level = (int) floor($currentIndentLength / strlen($from));
                return str_repeat($to, $level);
            },
            subject: $code,
        );
        if ($out === null) {
            throw new LogicException('Failed to convert indentation');
        }
        return $out;
    }

}
