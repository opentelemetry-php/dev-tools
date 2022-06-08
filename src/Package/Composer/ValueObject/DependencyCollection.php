<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\Composer\ValueObject;

use ArrayIterator;

class DependencyCollection extends AbstractCollection
{
    public static function create($array = [], $flags = 0, $iteratorClass = ArrayIterator::class): DependencyCollection
    {
        return new self($array, $flags, $iteratorClass);
    }

    public function getItemClass(): string
    {
        return DependencyInterface::class;
    }
}
