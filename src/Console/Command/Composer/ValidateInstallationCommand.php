<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Composer;

use Composer\Command\UpdateCommand;
use Generator;
use OpenTelemetry\DevTools\Console\Command\Composer\Behavior\CreatesOutputTrait;
use OpenTelemetry\DevTools\Console\Command\Composer\Behavior\UsesThirdPartyCommandTrait;
use OpenTelemetry\DevTools\Package\Composer\MultiRepositoryInfoResolver;
use OpenTelemetry\DevTools\Package\Composer\TestInstallationFactory;
use OpenTelemetry\DevTools\Package\Composer\TestInstaller;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\LocalRepository;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\RepositoryCollection;
use OpenTelemetry\DevTools\Package\Composer\ValueObject\SingleRepositoryInterface;
use OpenTelemetry\DevTools\Util\RecursiveDirectoryRemover;
use OpenTelemetry\DevTools\Util\WorkingDirectoryResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ValidateInstallationCommand extends Command
{
    use UsesThirdPartyCommandTrait;
    use CreatesOutputTrait;

    public const NAME = 'packages:validate:installation';
    public const DESCRIPTION = 'Validates composer files of the individual mono-repo packages';
    public const DIRECTORY_OPTION_NAME = 'directory';
    public const DIRECTORY_OPTION_SHORT = 'd';
    public const DIRECTORY_OPTION_MODE = InputOption::VALUE_OPTIONAL;
    public const DIRECTORY_OPTION_DESCRIPTION = 'Test installation base directory';
    public const PACKAGE_OPTION_NAME = 'package';
    public const PACKAGE_OPTION_SHORT = 'p';
    public const PACKAGE_OPTION_MODE = InputOption::VALUE_IS_ARRAY + InputOption::VALUE_OPTIONAL;
    public const PACKAGE_OPTION_DESCRIPTION = 'Default package to add to test installation -> "vendor/package:version';
    public const BRANCH_OPTION_NAME = 'branch';
    public const BRANCH_OPTION_SHORT = 'b';
    public const BRANCH_OPTION_MODE = InputOption::VALUE_OPTIONAL;
    public const BRANCH_OPTION_DESCRIPTION = 'Branch to test packages from';
    public const COMPOSER_FILE_NAME = 'composer.json';
    public const DEFAULT_INSTALLATION_DIRECTORY = '/tmp/_test';
    public const TEST_DIRECTORY = '_validate_installation_command';
    private const UPDATE_COMMAND_OPTIONS = [
        '--dry-run' => true,
        '--no-progress' => true,
        '--no-interaction' => true,
        '--no-plugins' => true,
    ];

    private static RecursiveDirectoryRemover $directoryRemover;

    private MultiRepositoryInfoResolver $resolver;
    private TestInstallationFactory $testInstallationFactory;
    private TestInstaller $testInstaller;
    private string $installationDirectory;
    private RepositoryCollection $packageInfos;
    private string $workingDirectory;

    public function __construct(
        MultiRepositoryInfoResolver $resolver,
        ?TestInstallationFactory $testInstallationFactory = null,
        ?TestInstaller $testInstaller = null
    ) {
        parent::__construct(self::NAME);

        $this->resolver = $resolver;
        $this->testInstallationFactory = $testInstallationFactory ?? TestInstallationFactory::create();
        $this->testInstaller = $testInstaller ?? TestInstaller::create(self::DEFAULT_INSTALLATION_DIRECTORY);
        $this->setInstallationDirectory($this->testInstaller->getRootDirectory());
        $this->initWorkingDirectory();
    }

    protected function configure(): void
    {
        $this->setDescription(self::DESCRIPTION);
        $this->addOption(
            self::DIRECTORY_OPTION_NAME,
            self::DIRECTORY_OPTION_SHORT,
            self::DIRECTORY_OPTION_MODE,
            self::DIRECTORY_OPTION_DESCRIPTION,
        );
        $this->addOption(
            self::PACKAGE_OPTION_NAME,
            self::PACKAGE_OPTION_SHORT,
            self::PACKAGE_OPTION_MODE,
            self::PACKAGE_OPTION_DESCRIPTION,
        );
        $this->addOption(
            self::BRANCH_OPTION_NAME,
            self::BRANCH_OPTION_SHORT,
            self::BRANCH_OPTION_MODE,
            self::BRANCH_OPTION_DESCRIPTION,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->setUpWorkingDirectory($input);

            $this->writeIntro($output);

            $this->setUpDefaultDependencies($input, $output);

            return $this->runInstallations($input, $output);
        } catch (Throwable $t) {
            $this->writeThrowable($output, $t);

            return Command::FAILURE;
        }
    }

    private function runInstallations(InputInterface $input, OutputInterface $output): int
    {

        /** @var SingleRepositoryInterface $repository */
        foreach ($this->getPackageInfos() as $repository) {
            $res = $this->runInstallation(
                $output,
                $repository,
                $this->getPackageInfos(),
                self::resolveBranchOptionValue($input)
            );

            if ($res !== self::SUCCESS) {
                return $res;
            }
        }

        return Command::SUCCESS;
    }

    private function runInstallation(
        OutputInterface $output,
        SingleRepositoryInterface $repository,
        RepositoryCollection $dependencies,
        ?string $branch = null
    ): int {
        if ($this->installPackage($output, $repository, $dependencies, $branch) !== 0) {
            $this->writeError(
                $output,
                sprintf(
                    'Failed to install %s',
                    $repository->getComposerFilePath()
                )
            );

            return Command::FAILURE;
        }

        $this->writeOk($output);

        return Command::SUCCESS;
    }

    protected function installPackage(
        OutputInterface $output,
        SingleRepositoryInterface $repository,
        RepositoryCollection $dependencies,
        ?string $branch = null
    ): int {
        $composerFile = $repository->getComposerFilePath();
        $packageName = $repository->getPackageName();
        $installationDirectory = $this->getInstallDirectory($composerFile);

        $installation = $this->testInstallationFactory->build(
            LocalRepository::create($installationDirectory, $repository->getPackage()),
            $branch,
            $dependencies
        );

        $installationDirectory = $installation->getTestedRepository()->getUrl();

        $this->writeBlankLine($output);
        $this->writeHeadline($output, $packageName);
        $this->writeComment($output, 'Install Dir: ' . $installationDirectory);
        $this->writeComment($output, 'Composer Source: ' . realpath($composerFile));
        $this->writeSeparator($output);
        $this->writeBlankLine($output);

        $this->testInstaller->install($installation);

        $res = $this->runUpdateCommand(
            $output,
            $installationDirectory
        );

        $this->testInstaller->remove($installation);

        return $res;
    }

    private function getInstallDirectory(string $composerFilePath): string
    {
        return $this->installationDirectory . DIRECTORY_SEPARATOR . str_replace('/', '_', dirname($composerFilePath));
    }

    protected function getPackageInfos(): RepositoryCollection
    {
        return $this->packageInfos ?? $this->packageInfos = $this->resolver->resolve();
    }

    private function initWorkingDirectory(): void
    {
        $this->workingDirectory = WorkingDirectoryResolver::create()->resolve();
    }

    private function setInstallationDirectory(string $directory): void
    {
        $this->installationDirectory = $directory . DIRECTORY_SEPARATOR . self::TEST_DIRECTORY;
    }

    private static function parsePackageString(string $package): array
    {
        return explode(':', $package);
    }

    private function setUpWorkingDirectory(InputInterface $input): void
    {
        if ($input->hasOption(self::DIRECTORY_OPTION_NAME)
            && $input->getOption(self::DIRECTORY_OPTION_NAME) !== null) {
            $this->setInstallationDirectory(
                $input->getOption(self::DIRECTORY_OPTION_NAME)
            );
        }
    }

    private function setUpDefaultDependencies(InputInterface $input, OutputInterface $output): void
    {
        $this->writeComment($output, 'with default packages: ');

        foreach (self::resolveDefaultDependencies($input) as $packageConfig) {
            $this->writeListItem($output, $packageConfig);
            $this->writeSeparator($output);
            [$package, $version] = self::parsePackageString($packageConfig);
            $this->testInstallationFactory->addDefaultDependency($package, $version);
        }
    }

    private static function resolveDefaultDependencies(InputInterface $input): Generator
    {
        foreach (self::resolveDependencyOptionValues($input) as $packageConfig) {
            yield $packageConfig;
        }
    }

    private static function resolveDependencyOptionValues(InputInterface $input): array
    {
        return $input->hasOption(self::PACKAGE_OPTION_NAME)
        && is_array($input->getOption(self::PACKAGE_OPTION_NAME))
            ? $input->getOption(self::PACKAGE_OPTION_NAME)
            : [];
    }

    private static function resolveBranchOptionValue(InputInterface $input): ?string
    {
        return $input->hasOption(self::BRANCH_OPTION_NAME)
            ? (string) $input->getOption(self::BRANCH_OPTION_NAME)
            : null;
    }

    private function runUpdateCommand(OutputInterface $output, string $workingDirectory = null): int
    {
        return $this->createAndRunCommand(
            UpdateCommand::class,
            new ArrayInput(self::UPDATE_COMMAND_OPTIONS),
            $output,
            $workingDirectory
        );
    }

    private function writeIntro(OutputInterface $output): void
    {
        $this->writeBlankLine($output);
        $this->writeHeadline($output, $this->getName());
        $this->writeComment($output, 'Trying to install packages from: ');
        $this->writeComment($output, $this->workingDirectory);
        $this->writeSeparator($output);
    }
}
