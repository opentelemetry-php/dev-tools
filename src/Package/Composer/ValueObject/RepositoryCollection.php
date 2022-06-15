<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\Composer\ValueObject;

class RepositoryCollection extends AbstractCollection
{
    public static function create($array = [], $flags = 0, $iteratorClass = 'ArrayIterator'): RepositoryCollection
    {
        return new self($array, $flags, $iteratorClass);
    }

    public function getItemClass(): string
    {
        return RepositoryInterface::class;
    }
}
