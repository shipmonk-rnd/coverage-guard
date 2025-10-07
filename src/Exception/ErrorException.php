<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Exception;

use RuntimeException;
use Throwable;

final class ErrorException extends RuntimeException
{

    public function __construct(
        string $message,
        ?Throwable $previous = null,
    )
    {
        parent::__construct($message, 0, $previous);
    }

}
