<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Package\Composer\ValueObject;

use OpenTelemetry\DevTools\Package\Composer\ValueObject\PackageInterface;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractRepositoryTest extends TestCase
{
    public const REPOSITORY_TYPE = 'path';
    public const REPOSITORY_URL = 'src/library';
    public const PACKAGE_TYPE = 'library';
    public const PACKAGE_NAME = 'foo/bar';
    public const ATTRIBUTES = [
        RepositoryInterface::TYPE_ATTRIBUTE => self::REPOSITORY_TYPE,
        RepositoryInterface::URL_ATTRIBUTE => self::REPOSITORY_URL,
        RepositoryInterface::PACKAGES_ATTRIBUTE => [
            self::PACKAGE_ATTRIBUTES,
        ],
    ];
    public const PACKAGE_ATTRIBUTES = [
        PackageInterface::TYPE_ATTRIBUTE => self::PACKAGE_TYPE,
        PackageInterface::NAME_ATTRIBUTE => self::PACKAGE_NAME,
    ];

    protected function createPackageInterfaceMock(): PackageInterface
    {
        $mock = $this->createMock(PackageInterface::class);

        $mock->method('toArray')
            ->willReturn(self::PACKAGE_ATTRIBUTES);

        $mock->method('jsonSerialize')
            ->willReturn(self::PACKAGE_ATTRIBUTES);

        $mock->method('getType')
            ->willReturn(self::PACKAGE_TYPE);

        $mock->method('getName')
            ->willReturn(self::PACKAGE_NAME);

        return $mock;
    }
}
