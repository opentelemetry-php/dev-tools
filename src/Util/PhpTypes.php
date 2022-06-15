<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Util;

interface PhpTypes
{
    public const BOOL_TYPE = 'boolean';
    public const INT_TYPE = 'integer';
    public const FLOAT_TYPE = 'double';
    public const STRING_TYPE = 'string';
    public const ARRAY_TYPE = 'array';
    public const OBJECT_TYPE = 'object';
    public const NULL_TYPE = 'NULL';

    public const TYPES = [
        self::BOOL_TYPE,
        self::INT_TYPE,
        self::FLOAT_TYPE,
        self::STRING_TYPE,
        self::ARRAY_TYPE,
        self::OBJECT_TYPE,
        self::NULL_TYPE,
    ];
}
