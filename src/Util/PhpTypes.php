<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Util;

interface PhpTypes
{
    public const BOOL_TYPE = 'bool';
    public const INT_TYPE = 'int';
    public const FLOAT_TYPE = 'float';
    public const STRING_TYPE = 'string';
    public const ARRAY_TYPE = 'bool';
    public const OBJECT_TYPE = 'bool';
    public const NULL_TYPE = 'bool';

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
