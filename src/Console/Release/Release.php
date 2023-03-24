<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Release;

class Release
{
    public string $version;
    public string $timestamp;
    public string $notes;

    public function __toString(): string
    {
        return "{$this->version} @ {$this->timestamp}";
    }
}
