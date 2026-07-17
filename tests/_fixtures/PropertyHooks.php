<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Fixtures;

use function strtolower;
use function strtoupper;

class PropertyHooks
{

    private string $name = '';

    public string $upper {
        get => strtoupper($this->name);
    }

    public string $checked {
        get {
            if ($this->name === '') {
                return 'empty';
            }
            return $this->name;
        }
        set {
            $this->name = strtolower($value);
        }
    }

}
