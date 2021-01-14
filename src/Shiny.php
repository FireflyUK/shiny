<?php declare(strict_types=1);

namespace Firefly;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Firefly\Shiny\Exception\InvalidPropertyException;
use Firefly\Shiny\Exception\PropertyNotExistException;
use Firefly\Shiny\Exception\PropertyOverrideException;
use Firefly\Shiny\Interface\ExportInterface;
use Firefly\Shiny\Interface\ImportInterface;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

class Shiny implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    public const BEHAVIOUR_OVERRIDE = 0;                // Replaces existing keys
    public const BEHAVIOUR_PROTECT_SILENT = 1;          // Protects existing keys, silently ignoring duplicates
    public const BEHAVIOUR_PROTECT_EXCEPTION = 2;       // Protects existing keys, throws on duplicate key

    private array $items;

    public function __construct(array|ImportInterface $config = [])
    {
        $this->import($config);
    }

    public function import(array|ImportInterface $config = []): static
    {
        if (is_array($config)) {
            $this->items = $config;
        } else {
            $this->items = $config->toArray();
        }
        return $this;
    }

    public function export(ExportInterface $saveHandler): static
    {
        $saveHandler->export($this->items);
        return $this;
    }

    public function set(string $key, mixed $value, int $behaviour = self::BEHAVIOUR_OVERRIDE): static
    {
        if ($value instanceof ImportInterface) {
            $value = $value->toArray();
        }

        $tokens = $this->getTokens($key);
        $section = &$this->items;

        while (count($tokens) > 1) {
            $token = array_shift($tokens);
            if (!isset($section[$token])) {
                $section[$token] = [];
            }

            $section = &$section[$token];
        }
        $token = array_shift($tokens);

        if (is_array($value)) {
            $this->checkForInvalidProperties($value, $key);
        }

        switch ($behaviour) {
            case self::BEHAVIOUR_PROTECT_EXCEPTION:
                throw new PropertyOverrideException("Property already exists at key {$key}", 1001);
            case self::BEHAVIOUR_PROTECT_SILENT:
                return $this;
            default:
            case self::BEHAVIOUR_OVERRIDE:
                if (!is_array($section)) {
                    $section = [];      //If current occupant of property is not an array, overwrite with blank array.
                }
                break;
        }

        $section[$token] = $value;

        return $this;
    }

    private function checkForInvalidProperties(array $value, string $key): void
    {
        if (is_array($value)) {
            array_walk_recursive($value, function ($v, $k) use ($key) {
                if (!is_scalar($v)) {
                    throw new InvalidPropertyException("Property must be Scalar: {$key}", 1001);
                }
            });
        } else if (!is_scalar($value)) {
            throw new InvalidPropertyException("Property must be Scalar: {$key}", 1001);
        }
    }

    public function get(string $key = "", mixed $default = null): mixed
    {
        $tokens = $this->getTokens($key);
        $section = &$this->items;

        while (count($tokens) > 1) {
            $token = array_shift($tokens);
            if (!isset($section[$token])) {
                throw new PropertyNotExistException("Property does not exist at key: {$key}", 1001);
            }

            $section = &$section[$token];
        }
        $token = array_shift($tokens);
        return $section[$token];
    }

    public function delete(string $key): static
    {
        $tokens = $this->getTokens($key);
        $section = &$this->items;

        while (count($tokens) > 1) {
            $token = array_shift($tokens);
            if (!isset($section[$token])) {
                throw new PropertyNotExistException("Property does not exist at key: {$key}", 1001);
            }

            $section = &$section[$token];
        }
        $token = array_shift($tokens);
        unset($section[$token]);

        return $this;
    }

    public function exists(string $key): bool
    {
        $tokens = $this->getTokens($key);

        while (count($tokens) > 1) {
            $token = array_shift($tokens);
            if (!isset($section[$token])) {
                return false;
            }

            $section = &$section[$token];
        }

        $token = array_shift($tokens);
        return isset($section[$token]);

    }

    private function getTokens($key): array
    {
        return explode('.', $key);
    }


    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    public function offsetGet($offset)
    {
        return $this->items[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function jsonSerialize()
    {
        return $this->items;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}