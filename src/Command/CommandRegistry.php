<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use function array_values;

final class CommandRegistry
{

    /**
     * @var array<string, Command>
     */
    private array $commands = [];

    public function register(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    /**
     * @throws ErrorException
     */
    public function getCommand(string $name): Command
    {
        if (!isset($this->commands[$name])) {
            throw new ErrorException("Unknown command: {$name}");
        }

        return $this->commands[$name];
    }

    /**
     * @return list<Command>
     */
    public function getAllCommands(): array
    {
        return array_values($this->commands);
    }

}
