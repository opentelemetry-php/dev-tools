<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\Composer\ValueObject;

use ArrayObject;
use InvalidArgumentException;

class RepositoryCollection extends ArrayObject
{
    public static function create($array = [], $flags = 0, $iteratorClass = 'ArrayIterator'): RepositoryCollection
    {
        return new self($array, $flags, $iteratorClass);
    }

    public function offsetExists($key): bool
    {
        self::ensureOffsetType($key);

        return parent::offsetExists($key);
    }

    public function offsetGet($key): RepositoryInterface
    {
        self::ensureOffsetType($key);

        return parent::offsetGet($key);
    }

    public function offsetSet($key, $value): void
    {
        self::ensureOffsetType($key);
        self::ensureValueType($value);

        parent::offsetSet($key, $value);
    }

    public function offsetUnset($key): void
    {
        self::ensureOffsetType($key);

        parent::offsetUnset($key);
    }

    private static function ensureOffsetType($key): void
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                sprintf('Offset must be of type string. Given "%s".', gettype($key))
            );
        }
    }

    private static function ensureValueType($value): void
    {
        if (!$value instanceof RepositoryInterface) {
            throw new InvalidArgumentException(
                sprintf(
                    'Value must be instance of "%s". Given "%s".',
                    RepositoryInterface::class,
                    get_debug_type($value)
                )
            );
        }
    }
}
