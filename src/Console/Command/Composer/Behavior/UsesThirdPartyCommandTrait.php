<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Composer\Behavior;

use Composer\Command\BaseCommand as ComposerBaseCommand;
use Composer\Composer;
use Composer\Console\Application as ComposerApplication;
use Composer\Factory;
use Composer\IO\NullIO;
use InvalidArgumentException;
use OpenTelemetry\DevTools\Util\WorkingDirectoryResolver;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

trait UsesThirdPartyCommandTrait
{
    protected function createAndRunCommand(
        string $commandClass,
        InputInterface $input,
        OutputInterface $output,
        string $workingDirectory = null
    ): int {
        return $this->runCommand(
            $this->createCommand($commandClass),
            $input,
            $output,
            $workingDirectory
        );
    }

    protected function runCommand(
        Command $command,
        InputInterface $input,
        OutputInterface $output,
        string $workingDirectory = null
    ): int {
        try {
            $oldWorkingDir = WorkingDirectoryResolver::create()->resolve();

            if (is_dir($workingDirectory) && is_dir($workingDirectory)) {
                chdir($workingDirectory);
            }

            return $command->run($input, $output);
        } catch (Throwable $t) {
            throw new RuntimeException(
                sprintf('Failed to run command "%s". Error: "%s" ', get_class($command), $t->getMessage()),
                $t->getCode(),
                $t
            );
        } finally {
            chdir($oldWorkingDir);
        }
    }

    protected function createCommand(string $commandClass): Command
    {
        self::ensureCommandClass($commandClass);

        return $this->initCommand(new $commandClass());
    }

    protected function initCommand(Command $command): Command
    {
        $command->setApplication(
            $this->getApplication()
        );

        if ($command instanceof ComposerBaseCommand) {
            $this->addComposer($command);
        }

        return $command;
    }

    protected function addComposer(ComposerBaseCommand $command): void
    {
        $command->setComposer(
            $this->createComposer()
        );

        $command->setApplication(
            $this->createComposerApplication()
        );
    }

    protected function createComposer(): Composer
    {
        return Factory::create(new NullIO());
    }

    protected function createComposerApplication(): ComposerApplication
    {
        return new ComposerApplication();
    }

    protected static function ensureCommandClass(string $commandClass): void
    {
        try {
            $classReflection = new ReflectionClass($commandClass);
            if (!$classReflection->isSubclassOf(Command::class)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Command class "%s" must extend "%s"',
                        $commandClass,
                        Command::class
                    )
                );
            }
        } catch (Throwable $t) {
            throw new InvalidArgumentException(
                sprintf(
                    'Could not validate class "%s"."',
                    $commandClass
                ),
                $t->getCode(),
                $t
            );
        }
    }

    /**
     * @return Application|null
     */
    abstract public function getApplication();
}
