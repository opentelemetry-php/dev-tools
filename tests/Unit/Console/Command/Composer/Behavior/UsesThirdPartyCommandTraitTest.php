<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Console\Command\Composer\Behavior;

use Composer\Command\BaseCommand as ComposerBaseCommand;
use Composer\Console\Application as ComposerApplication;
use Exception;
use InvalidArgumentException;
use OpenTelemetry\DevTools\Console\Command\Composer\Behavior\UsesThirdPartyCommandTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \OpenTelemetry\DevTools\Console\Command\Composer\Behavior\UsesThirdPartyCommandTrait
 */
class UsesThirdPartyCommandTraitTest extends TestCase
{
    private object $instance;
    private Application $application;

    protected function setUp(): void
    {
        $this->instance = $this->createInstance();
    }

    public function test_create_command(): void
    {
        $command = $this->instance->doCreateCommand(
            TestCommand::class
        );

        $this->assertSame(
            $this->getApplication(),
            $command->getApplication()
        );
    }

    public function test_create_composer_command(): void
    {
        $this->assertInstanceOf(
            ComposerBaseCommand::class,
            $this->instance->doCreateCommand(
                ComposerTestCommand::class
            )
        );

        $this->assertInstanceOf(
            ComposerApplication::class,
            $this->instance->doCreateCommand(
                ComposerTestCommand::class
            )->getApplication()
        );
    }

    public function test_create_command_throws_exception_on_non_command_class(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->instance->doCreateCommand(
            stdClass::class
        );
    }

    public function test_create_command_throws_exception_on_non_class(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->instance->doCreateCommand(
            'foo'
        );
    }

    public function test_run_command(): void
    {
        $this->assertSame(
            Command::SUCCESS,
            $this->instance->doRunCommand(
                new TestCommand(),
                $this->createInputInterfaceMock(),
                $this->createOutputInterfaceMock(),
                __DIR__,
            )
        );

        $this->assertNotEquals(
            __DIR__,
            getcwd()
        );
    }

    public function test_run_command_throws_exception_on_command_class_exception(): void
    {
        $this->expectException(RuntimeException::class);

        $this->instance->doRunCommand(
            new TestCommand(
                Command::SUCCESS,
                true
            ),
            $this->createInputInterfaceMock(),
            $this->createOutputInterfaceMock(),
        );
    }

    public function test_create_and_run_command(): void
    {
        $this->assertSame(
            Command::SUCCESS,
            $this->instance->doCreateAndRunCommand(
                TestCommand::class,
                $this->createInputInterfaceMock(),
                $this->createOutputInterfaceMock(),
                '.'
            )
        );
    }

    /**
     * @psalm-suppress RedundantPropertyInitializationCheck
     */
    private function getApplication(): Application
    {
        return $this->application ?? $this->application = $this->createApplication();
    }

    private function createApplication(): Application
    {
        $application = $this->createMock(Application::class);
        $application->method('getHelperSet')
            ->willReturn(
                $this->createMock(HelperSet::class)
            );

        return $application;
    }

    private function createInstance(): object
    {
        $application = $this->getApplication();

        return new class($application) {
            use UsesThirdPartyCommandTrait;

            private Application $application;

            public function __construct(Application $application)
            {
                $this->application = $application;
            }

            public function doCreateAndRunCommand(
                string $commandClass,
                InputInterface $input,
                OutputInterface $output,
                string $workingDirectory = null
            ): int {
                return $this->createAndRunCommand(
                    $commandClass,
                    $input,
                    $output,
                    $workingDirectory
                );
            }

            public function doRunCommand(
                Command $command,
                InputInterface $input,
                OutputInterface $output,
                string $workingDirectory = null
            ): int {
                return $this->runCommand(
                    $command,
                    $input,
                    $output,
                    $workingDirectory
                );
            }

            public function doCreateCommand(string $commandClass): Command
            {
                return $this->createCommand($commandClass);
            }

            public function doAddComposer(ComposerBaseCommand $command): void
            {
                $this->addComposer($command);
            }

            /**
             * @psalm-suppress ArgumentTypeCoercion
             */
            public function doEnsureCommandClass(string $commandClass)
            {
                /** @phpstan-ignore-next-line */
                return self::ensureCommandClass($commandClass);
            }

            public function getApplication(): Application
            {
                return $this->application;
            }
        };
    }

    private function createInputInterfaceMock(): InputInterface
    {
        return $this->createMock(InputInterface::class);
    }

    private function createOutputInterfaceMock(): OutputInterface
    {
        return $this->createMock(OutputInterface::class);
    }
}

class TestCommand extends Command
{
    public bool $hasRun = false;
    private int $runResult;
    private bool $throwException;

    public function __construct(int $runResult = Command::SUCCESS, bool $throwException = false)
    {
        $this->runResult = $runResult;
        $this->throwException = $throwException;

        parent::__construct('test/command');
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        if ($this->throwException) {
            throw new Exception('Error');
        }

        $this->hasRun = true;

        return $this->runResult;
    }
}

class ComposerTestCommand extends ComposerBaseCommand
{
}
