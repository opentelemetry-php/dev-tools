<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Package\Composer\ValueObject;

use OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryInterface;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\SingleRepositoryInterface;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\SingleRepositoryTrait;

/**
 * @covers \OpenTelemetry\DevTools\Package\Composer\ValueObject\SingleRepositoryTrait
 */
class SingleRepositoryTraitTest extends AbstractRepositoryTraitTest
{
    public const REPOSITORY_TYPE = 'path';
    private const COMPOSER_FILE_PATH = self::ATTRIBUTES[RepositoryInterface::URL_ATTRIBUTE]
        . DIRECTORY_SEPARATOR . SingleRepositoryInterface::COMPOSER_FILE_NAME;

    private SingleRepositoryInterface $repository;

    public function setUp(): void
    {
        $this->repository = $this->createInstance();
    }

    public function test_get_composer_file_path(): void
    {
        $this->assertSame(
            self::COMPOSER_FILE_PATH,
            $this->repository->getComposerFilePath()
        );
    }

    public function test_get_package(): void
    {
        $this->assertEquals(
            $this->createPackageInterfaceMock(),
            $this->repository->getPackage()
        );
    }

    public function test_get_package_name(): void
    {
        $this->assertEquals(
            self::PACKAGE_NAME,
            $this->repository->getPackageName()
        );
    }

    public function test_get_package_type(): void
    {
        $this->assertEquals(
            self::PACKAGE_TYPE,
            $this->repository->getPackageType()
        );
    }

    private function createInstance(): SingleRepositoryInterface
    {
        return SingleRepositoryTraitImplementation::create(
            self::ATTRIBUTES[RepositoryInterface::URL_ATTRIBUTE],
            $this->createPackageInterfaceMock()
        );
    }
}

class SingleRepositoryTraitImplementation implements SingleRepositoryInterface
{
    use SingleRepositoryTrait;

    protected function setType(): void
    {
        $this->type = SingleRepositoryTraitTest::REPOSITORY_TYPE;
    }
}
