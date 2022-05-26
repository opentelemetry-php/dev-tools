<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Package\Composer;

use InvalidArgumentException;
use OpenTelemetry\DevTools\Package\Composer\GitSplitConfigResolver;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use PHPUnit\Framework\TestCase;

class GitSplitConfigResolverTest extends TestCase
{
    private const ROOT_DIR = 'root';
    private const GITSPLIT_CONGIG_PATH = self::ROOT_DIR . '/.gitsplit.yml';
    private const PACKAGE_PATHS = [
        'A' => 'src/A',
        'B' => 'src/B',
        'C' => 'src/C',
    ];

    private vfsStreamDirectory $root;

    public function setUp(): void
    {
        $this->root = vfsStream::setup(self::ROOT_DIR);
    }

    public function test_resolve(): void
    {
        $resolver = new GitSplitConfigResolver(
            $this->getConfigFile(
                $this->getConfigContent()
            )->url()
        );

        $this->assertSame(
            $this->getExpectedResolverResult(),
            $resolver->resolve()
        );
    }

    public function test_resolve_paths_with_trailing_slash(): void
    {
        $resolver = new GitSplitConfigResolver(
            $this->getConfigFile(
                $this->getConfigContentWithTrailingSlash()
            )->url()
        );

        $this->assertSame(
            $this->getExpectedResolverResult(),
            $resolver->resolve()
        );
    }

    public function test_resolve_paths_with_no_split_config(): void
    {
        $resolver = new GitSplitConfigResolver(
            $this->getConfigFile(
                $this->getConfigContentWithNoSplitConfig()
            )->url()
        );

        $this->assertEmpty(
            $resolver->resolve()
        );
    }

    public function test_resolve_throws_exception_on_invalid_yaml(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GitSplitConfigResolver(
            $this->getConfigFile(
                $this->getInvalidYamlConfig()
            )->url()
        ))->resolve();
    }

    private function getExpectedResolverResult(): array
    {
        $result = [];

        foreach (self::PACKAGE_PATHS as $path) {
            $result[] = sprintf(
                '%s/composer.json',
                $path
            );
        }

        return $result;
    }

    private function getConfigFile(string $content): vfsStreamFile
    {
        return vfsStream::newFile(self::GITSPLIT_CONGIG_PATH)
            ->withContent($content)
            ->at($this->root);
    }

    private function getConfigContent(): string
    {
        $a = self::PACKAGE_PATHS['A'];
        $b = self::PACKAGE_PATHS['B'];
        $c = self::PACKAGE_PATHS['C'];

        return <<<EOF
cache_url: "/cache/gitsplit"

splits:
  - prefix: "$a"
    target: "https://example.com/example-php/A.git"
  - prefix: "$b"
    target: "https://example.com/example-php/B.git"
  - prefix: "$c"
    target: "https://example.com/example-php/C.git"

origins:
  - ^main$
  - ^v\d+\.\d+\.\d+$
  - ^\d+\.\d+\.\d+$
EOF;
    }

    private function getConfigContentWithTrailingSlash(): string
    {
        $a = self::PACKAGE_PATHS['A'];
        $b = self::PACKAGE_PATHS['B'];
        $c = self::PACKAGE_PATHS['C'];

        return <<<EOF
cache_url: "/cache/gitsplit"

splits:
  - prefix: "$a/"
    target: "https://example.com/example-php/A.git"
  - prefix: "$b/"
    target: "https://example.com/example-php/B.git"
  - prefix: "$c/"
    target: "https://example.com/example-php/C.git"

origins:
  - ^main$
  - ^v\d+\.\d+\.\d+$
  - ^\d+\.\d+\.\d+$
EOF;
    }

    private function getConfigContentWithNoSplitConfig(): string
    {
        return <<<EOF
cache_url: "/cache/gitsplit"

splits:

origins:
  - ^main$
  - ^v\d+\.\d+\.\d+$
  - ^\d+\.\d+\.\d+$
EOF;
    }

    private function getInvalidYamlConfig(): string
    {
        return <<<EOF
foo: bar
foo: baz
EOF;
    }
}
