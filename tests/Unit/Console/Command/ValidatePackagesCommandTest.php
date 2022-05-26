<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Console\Command\Composer;

use OpenTelemetry\DevTools\Console\Command\Composer\ValidatePackagesCommand;
use OpenTelemetry\DevTools\Package\Composer\ConfigResolverInterface;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ValidatePackagesCommandTest extends TestCase
{
    private const ROOT_DIR = 'root';
    private const VALID_COMPOSER_FILE = __DIR__ . '/_files/composer.valid.json';
    private const INVALID_COMPOSER_FILE = __DIR__ . '/_files/composer.invalid.json';
    private const BROKEN_COMPOSER_FILE = __DIR__ . '/_files/composer.broken.json';

    private vfsStreamDirectory $root;

    public function setUp(): void
    {
        $this->root = vfsStream::setup(self::ROOT_DIR);
    }

    public function test_execute_valid(): void
    {
        $paths = [
            self::VALID_COMPOSER_FILE,
            self::VALID_COMPOSER_FILE,
            self::VALID_COMPOSER_FILE,
        ];

        $commandTester = new CommandTester(
            new ValidatePackagesCommand(
                $this->createConfigResolverMock($paths)
            )
        );
        $commandTester->execute([]);

        $this->assertSame(
            Command::SUCCESS,
            $commandTester->getStatusCode()
        );
    }

    public function test_execute_invalid(): void
    {
        $paths = [
            self::VALID_COMPOSER_FILE,
            self::INVALID_COMPOSER_FILE,
            self::VALID_COMPOSER_FILE,
        ];

        $commandTester = new CommandTester(
            new ValidatePackagesCommand(
                $this->createConfigResolverMock($paths)
            )
        );
        $commandTester->execute([]);

        $this->assertSame(
            Command::FAILURE,
            $commandTester->getStatusCode()
        );
    }

    public function test_execute_broken(): void
    {
        $paths = [
            self::VALID_COMPOSER_FILE,
            self::BROKEN_COMPOSER_FILE,
            self::VALID_COMPOSER_FILE,
        ];

        $commandTester = new CommandTester(
            new ValidatePackagesCommand(
                $this->createConfigResolverMock($paths)
            )
        );
        $commandTester->execute([]);

        $this->assertSame(
            Command::FAILURE,
            $commandTester->getStatusCode()
        );
    }

    /**
     * @psalm-return MockObject|ConfigResolverInterface
     */
    private function createConfigResolverMock(array $paths): ConfigResolverInterface
    {
        $mock = $this->createMock(ConfigResolverInterface::class);

        $mock->method('resolve')
            ->willReturn($paths);

        return $mock;
    }

    private function createConfigFile(string $path, string $content): vfsStreamFile
    {
        return vfsStream::newFile($path)
            ->withContent($content)
            ->at($this->root);
    }

    private function getValidComposerConfig(): string
    {
        return json_encode([
            'name' => 'test/composer',
            'description' => 'test',
            'type' => 'library',
            'license' => 'Apache-2.0',
            'autoload' => [
                'psr-4' => [
                    'Test\\' => 'test/',
                ],
            ],
            'require' => [
                'phpunit/phpunit' => '^8.0',
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function getInvalidComposerConfig(): string
    {
        return '{}';
    }
}
