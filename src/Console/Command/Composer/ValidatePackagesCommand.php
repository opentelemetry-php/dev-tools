<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Composer;

use Composer\Command\ValidateCommand;
use OpenTelemetry\DevTools\Package\Composer\ConfigResolverInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ValidatePackagesCommand extends Command
{
    public const NAME = 'packages:composer:validate';
    public const DESCRIPTION = 'Validates composer files of the individual packages';

    private ConfigResolverInterface $resolver;

    public function __construct(ConfigResolverInterface $resolver)
    {
        parent::__construct(self::NAME);

        $this->resolver = $resolver;
    }

    protected function configure(): void
    {
        $this->setDescription(self::DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configs = $this->resolver->resolve();

        foreach ($configs as $composerFile) {
            try {
                $output->writeln('');
                $output->writeln(sprintf(
                    'Validating: %s',
                    $composerFile
                ));

                $res = $this->runValidateCommand($composerFile);

                if ($res !== 0) {
                    $output->writeln(sprintf(
                        '<fg=red>Error Validating: %s</>',
                        $composerFile
                    ));

                    return self::FAILURE;
                }

                $output->writeln('<fg=green>OK!</>');
            } catch (\Exception $e) {
                $output->writeln(sprintf(
                    '<fg=red>Error Validating %s : %s</>',
                    $composerFile,
                    $e->getMessage()
                ));

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function runValidateCommand(string $composerFile): int
    {
        return $this->createValidateCommand()
            ->run(
                new ArrayInput([
                'file' => $composerFile,
            ]),
                new ConsoleOutput(
                    OutputInterface::VERBOSITY_DEBUG
                )
            );
    }

    private function createValidateCommand(): ValidateCommand
    {
        $validateCommand = new ValidateCommand();
        $validateCommand->setApplication(
            $this->getApplication()
        );

        return $validateCommand;
    }
}
