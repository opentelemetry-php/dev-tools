<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Packages;

use Composer\Command\ValidateCommand;
use OpenTelemetry\DevTools\Console\Command\BaseCommand;
use OpenTelemetry\DevTools\Console\Command\Packages\Behavior\UsesThirdPartyCommandTrait;
use OpenTelemetry\DevTools\Package\Composer\ConfigResolverInterface;
use OpenTelemetry\DevTools\Util\WorkingDirectoryResolver;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ValidatePackagesCommand extends BaseCommand
{
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
        $this->registerInputAndOutput($input, $output);

        $this->writeIntro();

        $configs = $this->resolver->resolve();

        foreach ($configs as $composerFile) {
            try {
                $this->writeBlankLine();
                $this->writeSection(sprintf(
                    'Validating: %s',
                    $composerFile
                ));

                $res = $this->runValidateCommand($composerFile);

                if ($res !== 0) {
                    $this->writeError(sprintf(
                        'Composer file is invalid %s',
                        $composerFile
                    ));

                    return self::FAILURE;
                }

                $this->writeOk();
            } catch (Throwable $t) {
                $this->writeThrowable($t);

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
            ),
            WorkingDirectoryResolver::create()->resolve()
        );
    }

    private function writeIntro(): void
    {
        $this->writeTitle();
        $this->writeSection('Validating composer files in: ' . WorkingDirectoryResolver::create()->resolve());
        $this->writeBlankLine();
    }
}
