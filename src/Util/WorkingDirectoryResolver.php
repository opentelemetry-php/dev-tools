<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Util;

class WorkingDirectoryResolver
{
    public const DEFAULT_WORKING_DIRECTORY = '.';

    private string $workingDirectory;
    private static ?bool $cwdTestResponse = null;

    public static function create(): self
    {
        return new self();
    }

    public function resolve(): string
    {
        if (!isset($this->workingDirectory)) {
            $this->workingDirectory = self::callGetCwd() !== false ? self::callGetCwd() : self::callRealPath();
        }

        return $this->workingDirectory;
    }

    public static function setCwdTestResponse(?bool $response): void
    {
        self::$cwdTestResponse = $response;
    }

    /**
     * @return false|string
     */
    private static function callGetCwd()
    {
        return self::$cwdTestResponse ?? getcwd();
    }

    private static function callRealPath(): string
    {
        return realpath(self::DEFAULT_WORKING_DIRECTORY);
    }
}
