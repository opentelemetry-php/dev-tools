<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Package\Composer;

use Generator;
use InvalidArgumentException;
use OpenTelemetry\DevTools\Package\Composer\PackageAttributeResolver;
use OpenTelemetry\DevTools\Util\PhpTypes;
use OpenTelemetry\DevTools\Tests\Unit\Behavior\UsesVfsConstants;
use OpenTelemetry\DevTools\Tests\Unit\Behavior\UsesVfsTrait;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenTelemetry\DevTools\Package\Composer\PackageAttributeResolver
 */
class PackageAttributeResolverTest extends TestCase
{
    use UsesVfsTrait;

    private const TYPE_ATTRIBUTE = 'type';
    private const AUTHORS_ATTRIBUTE = 'authors';
    private const REQUIRE_ATTRIBUTE = 'require';
    private const VALID_CONFIG = [
        self::TYPE_ATTRIBUTE => 'library',
        self::AUTHORS_ATTRIBUTE => [[
            'name' => 'Foo Bar'
        ]],
        self::REQUIRE_ATTRIBUTE => [
            'foo/bar' => '1.0.0'
        ]
    ];

    private const TYPE_MATRIX = [
        self::TYPE_ATTRIBUTE => [
            PhpTypes::STRING_TYPE,
            PhpTypes::INT_TYPE,
            PhpTypes::FLOAT_TYPE,
            PhpTypes::ARRAY_TYPE,
            PhpTypes::OBJECT_TYPE,
            PhpTypes::NULL_TYPE,
        ],
        self::AUTHORS_ATTRIBUTE => [
            PhpTypes::INT_TYPE,
            PhpTypes::FLOAT_TYPE,
            PhpTypes::ARRAY_TYPE,
            PhpTypes::OBJECT_TYPE,
            PhpTypes::NULL_TYPE,
        ],
        self::REQUIRE_ATTRIBUTE => [
            PhpTypes::INT_TYPE,
            PhpTypes::FLOAT_TYPE,
            PhpTypes::ARRAY_TYPE,
            PhpTypes::OBJECT_TYPE,
            PhpTypes::NULL_TYPE,
        ],

    ];

    private const COMPOSER_FILE_NAME = 'composer.json';



    public function setUp(): void
    {
        $this->setUpVcs();
    }

    /**
     * @dataProvider provideAttributes
     */
    public function test_resolve(string  $attribute, $value): void
    {
        $this->assertSame(
            $value,
            PackageAttributeResolver::create(
                $this->createConfigFile(
                    $this->createValidConfig()
                )
            )->resolve($attribute)
        );
    }

    /**
     * @dataProvider provideAttributeTypes
     */
    public function test_resolve_types(string $attribute, string $type): void
    {
        $this->assertSame(
            $type,
            gettype(
                PackageAttributeResolver::create(
                    $this->createConfigFile(
                        $this->createValidConfig()
                    )
                )->resolve($attribute, $type)
            )
        );
    }

    public function test_resolve_throws_exception_on_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PackageAttributeResolver::create(
            $this->createConfigFile(
                $this->createValidConfig()
            )
        )->resolve(self::TYPE_ATTRIBUTE, 'foo');
    }

    public function test_resolve_throws_exception_on_invalid_composer_file(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PackageAttributeResolver::create(
            $this->createConfigFile(
                '{asdasd'
            )
        )->resolve(self::TYPE_ATTRIBUTE, 'foo');
    }

    public function test_resolve_throws_exception_on_non_existing_composer_file(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PackageAttributeResolver::create(
            'foo.json'
        )->resolve(self::TYPE_ATTRIBUTE, 'foo');
    }

    public function test_get_composer_file_path(): void
    {
        $path = $this->createConfigFile(
            $this->createValidConfig()
        );

        $this->assertSame(  
            $path,
            PackageAttributeResolver::create($path)
                ->getComposerFilePath()
        );
    }

    public function provideAttributes(): Generator
    {
        foreach(self::VALID_CONFIG as $attribute => $value) {
            yield [$attribute, $value];
        }
    }

    public function provideAttributeTypes(): Generator
    {
        foreach(self::TYPE_MATRIX as $attribute => $types) {
            foreach($types as $type) {
                yield [$attribute, $type];
            }
        }
    }

    private function createConfigFile(string $content): string
    {
        return vfsStream::newFile(self::COMPOSER_FILE_NAME)
            ->withContent($content)
            ->at($this->root)
            ->url();
    }

    private function createValidConfig(): string
    {
        return json_encode(self::VALID_CONFIG, JSON_THROW_ON_ERROR);
    }

}
