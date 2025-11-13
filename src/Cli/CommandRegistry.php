<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use ShipMonk\CoverageGuard\Command\Command;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function array_keys;
use function array_values;
use function implode;

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
            throw new ErrorException("Unknown command: {$name}. First argument need to be valid command name, expected one of: " . implode(', ', array_keys($this->commands)));
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
