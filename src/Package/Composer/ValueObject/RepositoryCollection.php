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

    public function offsetExists($offset): bool
    {
        self::ensureOffsetType($offset);

        return parent::offsetExists($offset);
    }

    public function offsetGet($offset): RepositoryInterface
    {
        self::ensureOffsetType($offset);

        return parent::offsetGet($offset);
    }

    public function offsetSet($offset, $value): void
    {
        self::ensureOffsetType($offset);
        self::ensureValueType($value);

        parent::offsetSet($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        self::ensureOffsetType($offset);

        parent::offsetUnset($offset);
    }

    private static function ensureOffsetType($offset): void
    {
        if (!is_string($offset)) {
            throw new InvalidArgumentException(
                sprintf('Offset must be of type string. Given "%s".', gettype($offset))
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
