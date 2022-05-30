<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\Composer\ValueObject;

trait PackageTrait
{
    use ValueObjectTrait;

    private string $name;

    public function getName(): string
    {
        return $this->name;
    }

    abstract public function getType(): string;

    public function toArray(): array
    {
        return [
            PackageInterface::TYPE_ATTRIBUTE => $this->getType(),
            PackageInterface::NAME_ATTRIBUTE => $this->getName(),
        ];
    }
}
