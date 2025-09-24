<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Release;

use Http\Discovery\Psr18ClientDiscovery;
use OpenTelemetry\DevTools\Console\Release\Release;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

class ReleaseListCommand extends AbstractReleaseCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('release:list')
            ->setDescription('List the latest version of each package')
            ->addOption('token', ['t'], InputOption::VALUE_OPTIONAL, 'github token')
            ->addOption('stable', null, InputOption::VALUE_NONE, 'only show versions with a 1.0 release')
            ->addOption('unstable', null, InputOption::VALUE_NONE, 'only show versions without a 1.0 release')
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stable = $input->getOption('stable');
        $unstable = $input->getOption('unstable');
        if ($stable && $unstable) {
            throw new \InvalidArgumentException('Can only use one of stable/unstable');
        }
        $this->token = $input->getOption('token');
        $this->sources = self::AVAILABLE_REPOS;
        $this->client = Psr18ClientDiscovery::find();
        $this->parser = new Parser();
        $this->registerInputAndOutput($input, $output);

        try {
            $found = $this->find_repositories();
        } catch (\Exception $e) {
            $this->output->writeln("<error>{$e->getCode()} {$e->getMessage()}</error>");

            return Command::FAILURE;
        }
        if (count($found) === 0) {
            $this->output->writeln('<error>No repositories found!</error>');

            return Command::FAILURE;
        }
        $table = new Table($this->output);
        $table->setHeaders(['Repository', 'Latest Version']);
        $bar = new ProgressBar($this->output, count($found));
        $bar->start();
        foreach ($found as $repository) {
            $repository->latestRelease = $this->get_latest_release($repository);
            $bar->advance();
            if ($this->show($repository->latestRelease, $unstable, $stable)) {
                $table->addRow([$repository->downstream->project, $repository->latestRelease->version ?? 'none']);
            }
        }
        $bar->finish();
        $table->render();

        return Command::SUCCESS;
    }

    private function show(?Release $release, ?bool $unstable, ?bool $stable): bool
    {
        if (!$release || !$release->version) {
            return true;
        }
        if ($unstable === false && $stable === false) {
            return true;
        }
        if ($stable) {
            return version_compare($release->version, '1.0.0', 'ge');
        }

        return version_compare($release->version, '1.0.0', 'lt');
    }
}
