<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Composer;

use Composer\Command\ValidateCommand;
use OpenTelemetry\DevTools\Console\Command\Composer\Behavior\CreatesOutputTrait;
use OpenTelemetry\DevTools\Console\Command\Composer\Behavior\UsesThirdPartyCommandTrait;
use OpenTelemetry\DevTools\Package\Composer\ConfigResolverInterface;
use OpenTelemetry\DevTools\Util\WorkingDirectoryResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ValidatePackagesCommand extends Command
{
    use CreatesOutputTrait;
    use UsesThirdPartyCommandTrait;

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
        $this->writeBlankLine($output);
        $this->writeHeadline($output, $this->getName());
        $this->writeComment($output, 'Validating composer files in: ');
        $this->writeComment($output, WorkingDirectoryResolver::create()->resolve());
        $this->writeSeparator($output);

        $configs = $this->resolver->resolve();

        foreach ($configs as $composerFile) {
            try {
                $this->writeBlankLine($output);
                $this->writeHeadline($output, sprintf(
                    'Validating: %s',
                    $composerFile
                ));

                $res = $this->runValidateCommand($composerFile);

                if ($res !== 0) {
                    $this->writeError($output, sprintf(
                        'Composer file is invalid %s',
                        $composerFile
                    ));

                    return self::FAILURE;
                }

                $this->writeOk($output);
            } catch (Throwable $t) {
                $this->writeThrowable($output, $t);

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function runValidateCommand(string $composerFile): int
    {
        return $this->createAndRunCommand(
            ValidateCommand::class,
            new ArrayInput([
                'file' => $composerFile,
            ]),
            new ConsoleOutput(
                OutputInterface::VERBOSITY_DEBUG
            )
        );
    }
}
