<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Release;

class Repository
{
    public string $org; //org+repo, eg open-telemetry/opentelemetry-php
    public string $upstream; //the monorepo that this repo is split from
    public string $path; //path in upstream
    public string $downstream; //the read-only downstream repo (split target)
    public ?Release $latestRelease; //latest release
    /**
     * @var array<Commit>
     */
    public array $commits = [];
}
