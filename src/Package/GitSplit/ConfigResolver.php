<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\GitSplit;

use InvalidArgumentException;
use OpenTelemetry\DevTools\Package\Composer\ConfigResolverInterface;
use OpenTelemetry\DevTools\Util\WorkingDirectoryResolver;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ConfigResolver implements ConfigResolverInterface
{
    private const SPLITS_KEY = 'splits';
    private const PREFIX_KEY = 'prefix';
    private const GIT_SPLIT_FILE_NAME = '.gitsplit.yml';
    private const COMPOSER_FILE_NAME = 'composer.json';

    private string $configFile;

    public function __construct(string $configFile = null)
    {
        $this->configFile = $configFile ?? $this->getDefaultConfigPath();
    }

    public function resolve(): array
    {
        $gitSplitConfig = self::parseGitSplitFile($this->configFile);

        if (!ConfigValidator::create($gitSplitConfig)->validate()) {
            return [];
        }

        return self::resolveComposerFiles($gitSplitConfig);
    }

    public function getDefaultConfigPath(): string
    {
        return sprintf('%s/%s', WorkingDirectoryResolver::create()->resolve(), self::GIT_SPLIT_FILE_NAME);
    }

    private static function parseGitSplitFile(string $filePath): array
    {
        try {
            return Yaml::parseFile($filePath);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(
                sprintf('Could not parse GitSplit file at: %s. %s', $filePath, $e->getMessage())
            );
        }
    }

    private static function resolveComposerFiles(array $gitSplitConfig): array
    {
        $result = [];

        foreach ($gitSplitConfig[self::SPLITS_KEY] as $pathConfig) {
            if (isset($pathConfig[self::PREFIX_KEY]) && is_string($pathConfig[self::PREFIX_KEY])) {
                $result[] = self::resolveComposerFilePath($pathConfig[self::PREFIX_KEY]);
            }
        }

        return $result;
    }

    private static function resolveComposerFilePath(string $directory): string
    {
        if (substr($directory, -1) !== '/') {
            $directory .= '/';
        }

        return sprintf(
            '%s%s',
            $directory,
            self::COMPOSER_FILE_NAME
        );
    }
}
