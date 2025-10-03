<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

/**
 * @api
 */
final class CoverageError
{

    private function __construct(
        private readonly string $message,
    )
    {
    }

    public static function message(string $message): self
    {
        return new self($message);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

}
