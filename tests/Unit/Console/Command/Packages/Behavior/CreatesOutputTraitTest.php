<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Console\Command\Packages\Behavior;

use Exception;
use Generator;
use OpenTelemetry\DevTools\Console\Command\Packages\Behavior\CreatesOutputTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \OpenTelemetry\DevTools\Console\Command\Packages\Behavior\CreatesOutputTrait
 */
class CreatesOutputTraitTest extends TestCase
{
    private const METHODS_WITH_MESSAGE_PARAMETER = [
        'writeSingleLine',
        'writeComment',
        'writeListItem',
        'writeHeadline',
        'writeGreenLine',
        'writeRedLine',
        'writeError',
    ];
    private const METHODS_WITHOUT_MESSAGE_PARAMETER = [
        'writeSeparator',
        'writeBlankLine',
        'writeOk',
    ];

    /**
     * @dataProvider provideMethodsWithMessageParameter
     */
    public function test_write_line_with_message(string $method): void
    {
        $this->createInstance()
            ->{$method}(
                $this->createOutputInterfaceMock(),
                'foo'
            );
    }

    /**
     * @dataProvider provideMethodsWithoutMessageParameter
     */
    public function test_write_line_without_message(string $method): void
    {
        $this->createInstance()
            ->{$method}(
                $this->createOutputInterfaceMock()
            );
    }

    public function test_write_colored_line(): void
    {
        $this->createInstance()
            ->writeColoredLine(
                $this->createOutputInterfaceMock(),
                'foo',
                'bar'
            );
    }

    public function test_write_throwable(): void
    {
        $this->createInstance()
            ->writeThrowable(
                $this->createOutputInterfaceMock(),
                new Exception(
                    'foo',
                    1,
                    new Exception('bar')
                )
            );
    }

    public function provideMethodsWithMessageParameter(): Generator
    {
        foreach (self::METHODS_WITH_MESSAGE_PARAMETER as $method) {
            yield [$method];
        }
    }

    public function provideMethodsWithoutMessageParameter(): Generator
    {
        foreach (self::METHODS_WITHOUT_MESSAGE_PARAMETER as $method) {
            yield [$method];
        }
    }

    private function createInstance(): object
    {
        return new class() {
            use CreatesOutputTrait;

            public function __call($name, $arguments)
            {
                $this->{$name}(...$arguments);
            }
        };
    }

    private function createOutputInterfaceMock(): OutputInterface
    {
        $mock = $this->createMock(OutputInterface::class);

        $mock->expects($this->atLeastOnce())
            ->method('writeln');

        return $mock;
    }
}
