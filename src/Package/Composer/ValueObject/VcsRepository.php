<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\Composer\ValueObject;

use OpenTelemetry\DevTools\Package\Composer\RepositoryTypes;

class VcsRepository implements SingleRepositoryInterface
{
    use SingleRepositoryTrait;

    protected function setType(): void
    {
        $this->type = RepositoryTypes::VCS_TYPE;
    }
}
