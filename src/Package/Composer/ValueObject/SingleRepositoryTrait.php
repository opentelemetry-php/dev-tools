<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\Composer\ValueObject;

trait SingleRepositoryTrait
{
    use RepositoryTrait ;

    private PackageInterface $package;

    public function __construct(string $url, PackageInterface $package)
    {
        $this->url = $url;
        $this->package = $package;
        $this->packages[] = $package;
        $this->setType();
    }

    public static function create(string $url, PackageInterface $package): self
    {
        return new self($url, $package);
    }

    public function getComposerFilePath(): string
    {
        return $this->getUrl() . DIRECTORY_SEPARATOR . SingleRepositoryInterface::COMPOSER_FILE_NAME;
    }

    public function getPackage(): PackageInterface
    {
        return $this->package;
    }

    public function getPackageName(): string
    {
        return $this->getPackage()->getName();
    }

    public function getPackageType(): string
    {
        return $this->getPackage()->getType();
    }

    abstract protected function setType(): void;
}
