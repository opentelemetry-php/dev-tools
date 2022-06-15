<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\Composer;

use InvalidArgumentException;
use OpenTelemetry\DevTools\Util\PhpTypes;

class PackageAttributeResolver
{
    private string $composerFilePath;
    private array $config;

    public function __construct(string $composerFilePath)
    {
        $this->composerFilePath = $composerFilePath;
    }

    public static function create(string $composerFilePath): self
    {
        return new self($composerFilePath);
    }

    /**
     * @return mixed
     */
    public function resolve(string $attributeName, ?string $type = null)
    {
        return $type === null
            ? $this->doResolve($attributeName)
            : $this->castType(
                $type,
                $this->doResolve($attributeName)
            );
    }

    public function getComposerFilePath(): string
    {
        return $this->composerFilePath;
    }

    /**
     * @return mixed
     */
    private function doResolve(string $attributeName)
    {
        return $this->getConfig()[$attributeName] ?? null;
    }

    /**
     * @return mixed
     */
    private function castType(string $type, $value)
    {
        if (!$this->validateType($type)) {
            throw new InvalidArgumentException(
                sprintf('Given type "%s" is not a valid PHP type', $type)
            );
        }

        settype($value, $type);

        return $value;
    }

    /**
     * @psalm-suppress RedundantPropertyInitializationCheck
     */
    private function getConfig(): array
    {
        try {
            return $this->config ?? $this->config = json_decode(
                file_get_contents($this->composerFilePath),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\Throwable $t) {
            throw new InvalidArgumentException(
                sprintf('Could not JSON decode composer file at: %s', $this->composerFilePath),
                (int) $t->getCode(),
                $t
            );
        }
    }

    private function validateType(string $type): bool
    {
        return in_array($type, PhpTypes::TYPES);
    }
}
