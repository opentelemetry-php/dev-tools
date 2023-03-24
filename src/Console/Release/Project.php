<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Release;

class Project
{
    public string $org;
    public string $project;
    public string $path; //for monorepo, path from which subtree split originates

    public function __construct(string $orgProject)
    {
        list($org, $project) = explode('/', $orgProject);
        $this->org = $org;
        $this->project = $project;
    }

    public function __toString(): string
    {
        return "{$this->org}/{$this->project}";
    }
}
