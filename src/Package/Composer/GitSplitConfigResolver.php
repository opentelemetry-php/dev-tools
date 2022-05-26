<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\Composer;

use InvalidArgumentException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class GitSplitConfigResolver implements ConfigResolverInterface
{
    private const SPLITS_KEY = 'splits';
    private const PREFIX_KEY = 'prefix';
    private const COMPOSER_FILE_NAME = 'composer.json';

    private string $configFile;

    public function __construct(string $configFile)
    {
        $this->configFile = $configFile;
    }

    public function resolve(): array
    {
        $gitSplitConfig = self::parseGitSplitFile($this->configFile);

        if (!self::validateConfigContent($gitSplitConfig)) {
            return [];
        }

        return self::resolveComposerFiles($gitSplitConfig);
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

    private static function resolveComposerFilePath(string $path): string
    {
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }

        return sprintf(
            '%s%s',
            $path,
            self::COMPOSER_FILE_NAME
        );
    }

    private static function validateConfigContent(array $config): bool
    {
        return isset($config[self::SPLITS_KEY]) && is_array($config[self::SPLITS_KEY]);
    }
}
