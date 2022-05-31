<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Package\Composer\ValueObject;

use Gitonomy\Git\Repository;
use InvalidArgumentException;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryCollection;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @covers \OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryCollection
 */
class RepositoryCollectionTest extends TestCase
{
    private RepositoryCollection $instance;

    private const OFFSET_KEY = 'foo';

    protected function setUp(): void
    {
        $this->instance = RepositoryCollection::create();
    }

    public function test_offset_access(): void
    {
        $repository = $this->createMock(RepositoryInterface::class);

        $this->instance[self::OFFSET_KEY] = $repository;

        $this->assertSame(
            $repository,
            $this->instance[self::OFFSET_KEY]
        );
    }

    public function test_offset_exists(): void
    {
        $this->instance[self::OFFSET_KEY] = $this->createMock(RepositoryInterface::class);

        $this->assertTrue(
            isset($this->instance[self::OFFSET_KEY])
        );
    }

    public function test_offset_unset(): void
    {
        $this->instance[self::OFFSET_KEY] = $this->createMock(RepositoryInterface::class);

        unset($this->instance[self::OFFSET_KEY]);

        $this->assertFalse(
            isset($this->instance[self::OFFSET_KEY])
        );
    }

    /**
     * @dataProvider provideInvalidOffsetTypes
     */
    public function test_offset_exists_throws_exception_on_invalid_offset($offset): void
    {
        $this->expectException(InvalidArgumentException::class);

        isset($this->instance[$offset]);
    }

    /**
     * @dataProvider provideInvalidOffsetTypes
     */
    public function test_offset_get_throws_exception_on_invalid_offset($offset): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->instance[$offset];
    }

    /**
     * @dataProvider provideInvalidOffsetTypes
     */
    public function test_offset_set_throws_exception_on_invalid_offset($offset): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->instance[$offset] = $this->createMock(RepositoryInterface::class);
    }

    /**
     * @dataProvider provideInvalidOffsetTypes
     */
    public function test_offset_unset_throws_exception_on_invalid_offset($offset): void
    {
        $this->expectException(InvalidArgumentException::class);

        unset($this->instance[$offset]);
    }

    /**
     * @dataProvider provideInvalidOffsetTypes
     */
    public function test_offset_set_throws_exception_on_invalid_value($value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->instance[self::OFFSET_KEY] = $value;
    }

    public function provideInvalidOffsetTypes(): array
    {
        return [
            [321],
            [3.21],
            [[]],
            [new stdClass()],
            [false],
            [null],
        ];
    }

    public function provideInvalidValueTypes(): array
    {
        return [
            ['foo'],
            [321],
            [3.21],
            [[]],
            [new stdClass()],
            [false],
            [null],
        ];
    }
}
