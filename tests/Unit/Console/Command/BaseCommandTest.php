<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Console\Command;

use Exception;
use Generator;
use OpenTelemetry\DevTools\Console\Command\BaseCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * @covers \OpenTelemetry\DevTools\Console\Command\BaseCommand
 */
class BaseCommandTest extends TestCase
{
    private const FORMATTED_MESSAGES = [
        'writeSection' => 'section',
        'writeLine' => 'writeln',
        'writeSuccess' => 'block',
        'writeError' => 'error',
    ];

    private ConcreteCommand $instance;
    private InputInterface $input;
    private OutputInterface $output;
    private SymfonyStyle $style;

    #[\Override]
    protected function setUp(): void
    {
        $this->instance = new ConcreteCommand();
        $this->instance->setInputAndOutput(
            $this->input = $this->createMock(InputInterface::class),
            $this->output = $this->createMock(OutputInterface::class)
        );
        $this->instance->setStyle(
            $this->style = $this->createMock(SymfonyStyle::class)
        );
    }

    public function test_get_input(): void
    {
        $this->assertSame(
            $this->input,
            $this->instance->getInput()
        );
    }

    public function test_get_output(): void
    {
        $this->assertSame(
            $this->output,
            $this->instance->getOutput()
        );
    }

    /**
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function test_get_style(): void
    {
        $style = $this->createMock(SymfonyStyle::class);
        $instance = new ConcreteCommand();
        $instance->setInputAndOutput(
            $this->input,
            $this->output
        );

        /** @phpstan-ignore-next-line */
        $this->output->method('getFormatter')
            ->willReturn($this->createMock(OutputFormatterInterface::class));

        $this->assertNotSame(
            $style,
            $instance->getStyle()
        );

        $instance->setStyle($style);

        $this->assertSame(
            $style,
            $instance->getStyle()
        );
    }

    /**
     * @psalm-suppress UndefinedMethod
     */
    public function test_write_title(): void
    {
        /** @phpstan-ignore-next-line */
        $this->style->expects($this->once())->method('title');

        $this->instance->doWriteTitle();
    }

    /**
     * @psalm-suppress UndefinedMethod
     */
    public function test_write_blank_line(): void
    {
        /** @phpstan-ignore-next-line */
        $this->style->expects($this->once())->method('newLine');

        $this->instance->doWriteBlankLine();
    }

    /**
     * @psalm-suppress UndefinedMethod
     */
    public function test_write_blank_listing(): void
    {
        /** @phpstan-ignore-next-line */
        $this->style->expects($this->once())->method('listing');

        $this->instance->doWriteListing([]);
    }

    /**
     * @psalm-suppress UndefinedMethod
     */
    public function test_write_ok(): void
    {
        /** @phpstan-ignore-next-line */
        $this->style->expects($this->once())->method('block');

        $this->instance->doWriteOk();
    }

    /**
     * @psalm-suppress UndefinedMethod
     */
    public function test_write_throwable(): void
    {
        /** @phpstan-ignore-next-line */
        $this->style->expects($this->atLeastOnce())->method('error');

        $this->instance->doWriteThrowable(
            new Exception('foo', 1, new Exception())
        );
    }

    /**
     * @dataProvider provideFormattedMessageCalls
     * @psalm-suppress UndefinedMethod
     */
    public function test_write_formatted_messages(string $method, string $styleMethod): void
    {
        /** @phpstan-ignore-next-line */
        $this->style->expects($this->once())->method($styleMethod);

        $this->instance->{$method}('foo');
    }

    public function provideFormattedMessageCalls(): Generator
    {
        foreach (self::FORMATTED_MESSAGES as $method => $styleMethod) {
            yield [$method, $styleMethod];
        }
    }
}

class ConcreteCommand extends BaseCommand
{
    public function setInput(InputInterface $input): void
    {
        $this->registerInput($input);
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->registerOutput($output);
    }

    public function setInputAndOutput(InputInterface $input, OutputInterface $output): void
    {
        $this->registerInputAndOutput($input, $output);
    }

    public function setStyle(SymfonyStyle $style): void
    {
        $this->style = $style;
    }

    public function doWriteTitle(): void
    {
        $this->writeTitle();
    }

    public function doWriteBlankLine(int $count = 1): void
    {
        $this->writeBlankLine($count);
    }

    public function doWriteListing(array $elements): void
    {
        $this->writeListing($elements);
    }

    public function doWriteThrowable(Throwable $throwable): void
    {
        $this->writeThrowable($throwable);
    }

    public function doWriteOk(): void
    {
        $this->writeOk();
    }

    public function __call($method, $args)
    {
        if (method_exists($this, $method)) {
            $this->$method(...$args);
        }
    }
}
