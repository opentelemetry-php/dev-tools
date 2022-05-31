<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Package\Composer;

use Generator;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\PackageFactory;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryCollection;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryFactory;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\SingleRepositoryInterface;
use OpenTelemetry\DevTools\Util\PhpTypes;

class MultiRepositoryInfoResolver
{
    private const NAME_ATTRIBUTE = 'name';
    private const TYPE_ATTRIBUTE = 'type';

    private ConfigResolverInterface $configResolver;
    private PackageAttributeResolverFactory $packageAttributeResolverFactory;
    private RepositoryFactory $repositoryFactory;
    private array $packageAttributeResolvers = [];

    public function __construct(
        ConfigResolverInterface $configResolver,
        ?PackageAttributeResolverFactory $packageAttributeResolverFactory = null,
        ?RepositoryFactory $repositoryFactory = null
    ) {
        $this->configResolver = $configResolver;
        $this->packageAttributeResolverFactory = $packageAttributeResolverFactory
            ?? PackageAttributeResolverFactory::create();
        $this->repositoryFactory = $repositoryFactory
            ?? RepositoryFactory::create(
                PackageFactory::create()
            );
    }

    public static function create(
        ConfigResolverInterface $configResolver,
        ?PackageAttributeResolverFactory $packageAttributeResolverFactory,
        ?RepositoryFactory $repositoryFactory = null
    ): MultiRepositoryInfoResolver {
        return new self($configResolver, $packageAttributeResolverFactory, $repositoryFactory);
    }

    public function resolve(): RepositoryCollection
    {
        $collection = RepositoryCollection::create();

        foreach ($this->doResolve() as $packageName => $repository) {
            $collection[$packageName] = $repository;
        }

        return $collection;
    }

    private function doResolve(): Generator
    {
        foreach ($this->configResolver->resolve() as $composerFile) {
            $repository = $this->createRepository($composerFile);

            yield $repository->getPackage()->getName() => $repository;
        }
    }

    private function createRepository(string $composerFile): SingleRepositoryInterface
    {
        return $this->repositoryFactory->buildSingleRepository(
            $this->getRepositoryPath($composerFile),
            RepositoryTypes::PATH_TYPE,
            $this->getPackageName($composerFile),
            $this->getPackageType($composerFile)
        );
    }

    private function getPackageType(string $composerFile): string
    {
        return (string) $this->getPackageNameResolver($composerFile)
            ->resolve(self::TYPE_ATTRIBUTE, PhpTypes::STRING_TYPE);
    }

    private function getPackageName(string $composerFile): string
    {
        return (string) $this->getPackageNameResolver($composerFile)
            ->resolve(self::NAME_ATTRIBUTE, PhpTypes::STRING_TYPE);
    }

    private function getPackageNameResolver(string $composerFile): PackageAttributeResolver
    {
        return $this->packageAttributeResolvers[$composerFile]
            ?? $this->packageAttributeResolvers[$composerFile] = $this->createPackageNameResolver($composerFile);
    }

    private function createPackageNameResolver(string $composerFile): PackageAttributeResolver
    {
        return $this->packageAttributeResolverFactory->build($composerFile);
    }

    private function getRepositoryPath(string $composerFile): string
    {
        return dirname($composerFile);
    }
}
