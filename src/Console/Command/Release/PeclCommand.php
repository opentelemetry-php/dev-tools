<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Release;

use DOMDocument;
use Exception;
use Http\Discovery\Psr18ClientDiscovery;
use OpenTelemetry\DevTools\Console\Release\Project;
use OpenTelemetry\DevTools\Console\Release\Repository;
use SimpleXMLElement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class PeclCommand extends AbstractReleaseCommand
{
    private const OWNER = 'open-telemetry';
    private const REPO = 'opentelemetry-php-instrumentation';
    private const REPOSITORY = self::OWNER . '/' . self::REPO;

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('release:pecl')
            ->setDescription('Update auto-instrumentation package.xml for PECL release')
            ->addOption('force', ['f'], InputOption::VALUE_NONE, 'force')
            ->addOption('version', null, InputOption::VALUE_OPTIONAL, 'new version (skips prompt)')
            ->addOption('stability', null, InputOption::VALUE_OPTIONAL, 'release stability: stable|beta (skips prompt)', null)
            ->addOption('output-file', null, InputOption::VALUE_OPTIONAL, 'write updated package.xml to this file path instead of stdout')
            ->addOption('update-header', null, InputOption::VALUE_OPTIONAL, 'path to local php_opentelemetry.h to update PHP_OPENTELEMETRY_VERSION')
        ;
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        //no-op
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');
        $this->client = Psr18ClientDiscovery::find();
        $this->registerInputAndOutput($input, $output);
        $project = new Project(self::REPOSITORY);
        $repository = new Repository();
        $repository->downstream = $project;
        $repository->upstream = $project;
        $repository->latestRelease = $this->get_latest_release($repository);
        if ($repository->latestRelease === null) {
            $this->output->writeln("<error>No latest release found for {$repository->upstream}</error>");

            return Command::FAILURE;
        }
        $repository->commits = $this->get_downstream_unreleased_commits($repository);
        if (count($repository->commits) === 0) {
            $this->output->writeln("<info>No unreleased commits since {$repository->latestRelease->version}</info>");
            if (!$force) {
                return Command::SUCCESS;
            }
        }

        $url = sprintf('https://raw.githubusercontent.com/%s/main/ext/package.xml', self::REPOSITORY);
        $response = $this->fetch($url);
        if ($response->getStatusCode() !== 200) {
            throw new Exception("Error fetching {$url}");
        }

        $xml = new SimpleXMLElement($response->getBody()->getContents());

        $this->process($repository, $xml);

        return Command::SUCCESS;
    }

    /**
     * @psalm-suppress PossiblyNullPropertyFetch
     */
    protected function process(Repository $repository, SimpleXMLElement $xml): void
    {
        $cnt = count($repository->commits);
        $this->output->writeln("<info>Last release {$repository->latestRelease->version} @ {$repository->latestRelease->timestamp}</info>");
        $this->output->writeln("<info>[{$repository->downstream}]</info> {$cnt} unreleased change(s):");
        foreach ($repository->commits as $commit) {
            $this->output->writeln("<comment>* [#{$commit->pullRequest->id}] {$commit->pullRequest->title} ({$commit->pullRequest->author})</comment>");
        }
        $prev = ($repository->latestRelease === null)
            ? '-nothing-'
            : $repository->latestRelease->version;

        $helper = new QuestionHelper();
        $newVersion = $this->input->getOption('version');
        if (!$newVersion) {
            $question = new Question("<question>Latest={$prev}, enter new tag (blank to skip):</question>", null);
            $newVersion = $helper->ask($this->input, $this->output, $question);
        }
        if (!$newVersion) {
            $this->output->writeln("<info>[SKIP] not going to release {$repository->downstream}</info>");

            return;
        }

        $stability = $this->input->getOption('stability');
        if (!$stability) {
            $question = new ChoiceQuestion(
                '<question>Is this a beta or stable release?</question>',
                ['stable', 'beta'],
                'stable',
            );
            $stability = $helper->ask($this->input, $this->output, $question);
        }

        //new release data
        $release = [
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'version' => [
                'release' => $newVersion,
                'api' => '1.0',
            ],
            'stability' => [
                'release' => $stability,
                'api' => 'stable',
            ],
            'notes' => $this->format_notes($newVersion),
        ];
        $xmlContent = $this->convertPackageXml($xml, $release);

        $outputFile = $this->input->getOption('output-file');
        if ($outputFile) {
            file_put_contents($outputFile, $xmlContent);
            $this->output->writeln("<info>[WRITTEN] package.xml -> {$outputFile}</info>");
        } else {
            $this->output->writeln($xmlContent);
        }

        $headerFile = $this->input->getOption('update-header');
        if ($headerFile) {
            $this->update_header_file($headerFile, $newVersion);
        }
    }

    protected function convertPackageXml(SimpleXMLElement $xml, array $new): string
    {
        //add current release to changelog
        $release = $xml->changelog->addChild('release');
        $release->addChild('date', (string) $xml->date);
        $release->addChild('time', (string) $xml->time);
        $version = $release->addChild('version');
        $version->addChild('release', (string) $xml->version->release);
        $version->addChild('api', (string) $xml->version->api);
        $stability = $release->addChild('stability');
        $stability->addChild('release', (string) $xml->stability->release);
        $stability->addChild('api', (string) $xml->stability->api);
        $release->addChild('license', (string) $xml->license);
        $release->addChild('notes', (string) $xml->notes);

        //update new release details
        $xml->date = $new['date'];
        $xml->time = $new['time'];
        $xml->version->release = $new['version']['release'];
        $xml->version->api = $new['version']['api'];
        $xml->stability->release = $new['stability']['release'];
        $xml->stability->api = $new['stability']['api'];
        $xml->notes = $new['notes'];

        //prettify
        $pretty = new DOMDocument();
        $pretty->preserveWhiteSpace = false;
        $pretty->formatOutput = true;
        $pretty->loadXML($xml->saveXML());

        return $pretty->saveXML();
    }

    private function update_header_file(string $path, string $version): void
    {
        if (!file_exists($path)) {
            $this->output->writeln("<error>[ERROR] Header file not found: {$path}</error>");

            return;
        }
        $contents = file_get_contents($path);
        $updated = preg_replace(
            '/(#define PHP_OPENTELEMETRY_VERSION ")[^"]+(")/m',
            '${1}' . $version . '${2}',
            $contents,
        );
        if ($updated === $contents) {
            $this->output->writeln('<comment>[WARN] PHP_OPENTELEMETRY_VERSION define not found in header file</comment>');

            return;
        }
        file_put_contents($path, $updated);
        $this->output->writeln("<info>[UPDATED] PHP_OPENTELEMETRY_VERSION -> {$version} in {$path}</info>");
    }

    protected function format_notes(string $version): string
    {
        return sprintf('See https://github.com/%s/%s/releases/tag/%s', self::OWNER, self::REPO, $version);
    }
}
