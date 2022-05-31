<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\Composer\ValueObject;

class Dependency implements DependencyInterface
{
    use ValueObjectTrait;

    private string $versionConstraint;
    private PackageInterface $package;

    public function __construct(string $getVersionConstraint, PackageInterface $package)
    {
        $this->versionConstraint = $getVersionConstraint;
        $this->package = $package;
    }

    public static function create(string $getVersionConstraint, PackageInterface $package): self
    {
        return new self($getVersionConstraint, $package);
    }

    public function getVersionConstraint(): string
    {
        return $this->versionConstraint;
    }

    public function getPackage(): PackageInterface
    {
        return $this->package;
    }

    public function toArray(): array
    {
        return [
            DependencyInterface::VERSION_ATTRIBUTE => $this->getVersionConstraint(),
            DependencyInterface::PACKAGE_ATTRIBUTE => $this->getPackage()->toArray(),
        ];
    }
}
