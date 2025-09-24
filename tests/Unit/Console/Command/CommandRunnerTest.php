<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Console\Command;

use Exception;
use OpenTelemetry\DevTools\Console\Command\CommandRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \OpenTelemetry\DevTools\Console\Command\CommandRunner
 */
class CommandRunnerTest extends TestCase
{
    private CommandRunner $instance;

    #[\Override]
    protected function setUp(): void
    {
        $this->instance = CommandRunner::create();
    }

    public function test_set_input(): void
    {
        $input = $this->createMock(InputInterface::class);

        $this->instance->setInput($input);

        $this->assertSame(
            $input,
            $this->instance->getInput()
        );
    }

    public function test_set_output(): void
    {
        $output = $this->createMock(OutputInterface::class);

        $this->instance->setOutput($output);

        $this->assertSame(
            $output,
            $this->instance->getOutput()
        );
    }

    public function test_run(): void
    {
        $command = $this->createMock(Command::class);

        $command->expects($this->once())
            ->method('run')
            ->willReturn(
                Command::SUCCESS
            );

        $this->instance->run($command);
    }

    public function test_run_rethrows_exception(): void
    {
        $this->expectException(RuntimeException::class);

        $command = $this->createMock(Command::class);

        $exception = new Exception();

        $command->expects($this->once())
            ->method('run')
            ->willThrowException($exception);

        $this->instance->run($command);
    }
}
