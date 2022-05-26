<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Application;

use OpenTelemetry\DevTools\Console\Command\Composer\ValidatePackagesCommand;
use OpenTelemetry\DevTools\Package\Composer\GitSplitConfigResolver;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public const DEFAULT_NAME = 'otel';

    public function __construct(string $name = self::DEFAULT_NAME, string $version = 'DEV')
    {
        parent::__construct($name, $version);

        $this->initCommands();
    }

    private function initCommands(): void
    {
        $this->addCommands([
            new ValidatePackagesCommand(
                new GitSplitConfigResolver()
            ),
        ]);
    }
}
