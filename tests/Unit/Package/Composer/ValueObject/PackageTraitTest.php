<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Package\Composer\ValueObject;

use OpenTelemetry\DevTools\Package\Composer\ValueObject\PackageInterface;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\PackageTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenTelemetry\DevTools\Package\Composer\ValueObject\PackageTrait
 */
class PackageTraitTest extends TestCase
{
    public const ATTRIBUTES = [
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

    private function createInstance(): PackageInterface
    {
        return new class() implements PackageInterface {
            use PackageTrait;

            public function __construct()
            {
                $this->name = PackageTraitTest::ATTRIBUTES[PackageInterface::NAME_ATTRIBUTE];
            }

            public function getType(): string
            {
                return PackageTraitTest::ATTRIBUTES[PackageInterface::TYPE_ATTRIBUTE];
            }
        };
    }
}