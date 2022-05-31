<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Package\Composer\ValueObject;

use OpenTelemetry\DevTools\Package\Composer\ValueObject\PackageInterface;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryInterface;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryTrait
 */
class RepositoryTraitTest extends TestCase
{
    public const ATTRIBUTES = [
        RepositoryInterface::TYPE_ATTRIBUTE => 'path',
        RepositoryInterface::URL_ATTRIBUTE => 'src/library',
        RepositoryInterface::PACKAGES_ATTRIBUTE => [
            self::PACKAGE_ATTRIBUTES,
        ],
    ];
    private const PACKAGE_ATTRIBUTES = [
        PackageInterface::TYPE_ATTRIBUTE => 'library',
        PackageInterface::NAME_ATTRIBUTE => 'foo/bar',
    ];

    public function test_to_array(): void
    {
        $this->assertSame(
            self::ATTRIBUTES,
            $this->createInstance()->toArray()
        );
    }
    
    private function createInstance(): RepositoryInterface
    {
        return new class(self::ATTRIBUTES[RepositoryInterface::URL_ATTRIBUTE], self::ATTRIBUTES[RepositoryInterface::TYPE_ATTRIBUTE], [$this->createPackageInterfaceMock()], ) implements RepositoryInterface {
            use RepositoryTrait;

            public function __construct(string $url, string $type, array $packages = [])
            {
                $this->url = $url;
                $this->type = $type;
                $this->packages = $packages;
            }
        };
    }

    private function createPackageInterfaceMock(): PackageInterface
    {
        $mock = $this->createMock(PackageInterface::class);

        $mock->method('toArray')
            ->willReturn(
                self::ATTRIBUTES[RepositoryInterface::PACKAGES_ATTRIBUTE][0]
            );

        return $mock;
    }
}
