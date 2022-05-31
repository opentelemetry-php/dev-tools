<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Util;

use OpenTelemetry\DevTools\Util\RecursiveDirectoryRemover;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

/**
 * Override realpath() in current namespace for testing
 *
 * @param string $path     the file path
 *
 * @return string
 */
function realpath($path)
{
    return $path;
}

class RecursiveDirectoryRemoverTest extends TestCase
{
    private const ROOT_DIR = 'root';
    private const TEST_DIR = 'test';
    private const FILES_DIR = self::TEST_DIR . DIRECTORY_SEPARATOR . 'files';
    private const DIRS_DIR = self::TEST_DIR . DIRECTORY_SEPARATOR . '_dirs';
    private const PATHS = [
        '.foo/foo',
        '.foo/.foo',
        '.foo/.foo/.foo',
        'foo/.foo',
        '.foo/bar/foo',
        '.foo/bar/.foo',
        'foo/bar/.foo',
        '_baz/.foo/foo',
        '_baz/.foo/.foo',
        '_baz/foo/.foo',
        '_baz/.foo/bar/foo',
        '_baz/.foo/bar/.foo',
        '_baz/foo/bar/.foo',
    ];

    private RecursiveDirectoryRemover $remover;
    private vfsStreamDirectory $root;

    public function setUp(): void
    {
        $this->remover = RecursiveDirectoryRemover::create(true);
        $this->root = vfsStream::setup(self::ROOT_DIR);
        //vfsStream::newDirectory(self::TEST_DIR, 0777)->at($this->root);
    }

    public function test_delete_files(): void
    {
        $testDirectory = $this->setUpFiles()->url();

        //$this->assertSame(self::FILES_DIR, $testDirectory);

        $this->assertTrue(
            $this->remover->remove($testDirectory)
        );

        $this->assertDirectoryDoesNotExist($testDirectory);
    }

    private function setUpFiles(): vfsStreamDirectory
    {
        $testDirectory = vfsStream::newDirectory(self::FILES_DIR, 0777)
            ->at($this->root);

        foreach (self::PATHS as $path) {
            vfsStream::newDirectory(dirname(self::FILES_DIR . DIRECTORY_SEPARATOR . $path), 0777)
            ->at($this->root);

            $file = vfsStream::newFile(self::FILES_DIR . DIRECTORY_SEPARATOR . $path)
                ->withContent('foo')
                ->at($this->root);

            echo PHP_EOL . $file->url();
            echo PHP_EOL . file_get_contents($file->url());
        }

        return $testDirectory;
    }
}
