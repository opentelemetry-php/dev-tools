<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class CommandRunner
{
    private ?InputInterface $input;
    private ?OutputInterface $output;

    public function __construct(?InputInterface $input = null, ?OutputInterface $output = null)
    {
        $this->input = $input;
        $this->output = $output;
    }

    public static function create(?InputInterface $input = null, ?OutputInterface $output = null): CommandRunner
    {
        return new self($input, $output);
    }

    public function run(Command $command, ?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        try {
            return $command->run(
                $input ?? $this->getInput(),
                $output ?? $this->getOutput()
            );
        } catch (Throwable $t) {
            throw new RuntimeException(
                sprintf('Could not run command "%s"', $command->getName() ?? get_class($command)),
                (int) $t->getCode(),
                $t
            );
        }
    }

    public function setInput(?InputInterface $input): void
    {
        $this->input = $input;
    }

    public function getInput(): InputInterface
    {
        return $this->input ?? $this->input = new ArrayInput([]);
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output ?? $this->output = new NullOutput();
    }
}
