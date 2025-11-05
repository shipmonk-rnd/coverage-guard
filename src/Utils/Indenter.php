<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use LogicException;
use function preg_quote;
use function preg_replace_callback;
use function str_repeat;
use function strlen;

final class Indenter
{

    /**
     * @param string $from Original indent expected in $code
     * @param string $to New indent to be used
     */
    public static function change(
        string $code,
        string $from,
        string $to,
    ): string
    {
        $escapedFrom = preg_quote($from, '/');
        $out = preg_replace_callback(
            pattern: '/^((?:' . $escapedFrom . ')+)/m',
            callback: static function (array $matches) use ($from, $to): string {
                $currentIndentLength = strlen($matches[1]); // @phpstan-ignore offsetAccess.notFound
                $level = $currentIndentLength / strlen($from);
                return str_repeat($to, (int) $level);
            },
            subject: $code,
        );
        if ($out === null) {
            throw new LogicException('Failed to convert indentation');
        }
        return $out;
    }

}
