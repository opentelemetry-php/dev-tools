<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Release;

use DOMDocument;
use Http\Discovery\HttpClientDiscovery;
use Jackiedo\XmlArray\Array2Xml;
use Jackiedo\XmlArray\Xml2Array;
use Nyholm\Psr7\Request;
use OpenTelemetry\DevTools\Console\Release\Commit;
use OpenTelemetry\DevTools\Console\Release\Project;
use OpenTelemetry\DevTools\Console\Release\Release;
use OpenTelemetry\DevTools\Console\Release\Repository;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Yaml\Parser;

class PeclCommand extends AbstractReleaseCommand
{
    private const REPOSITORY = 'open-telemetry/opentelemetry-php-instrumentation';
    private Serializer $serializer;
    private string $source_branch;
    private bool $dry_run;

    public function __construct(Serializer $serializer)
    {
        parent::__construct();
        $this->serializer = $serializer;
    }

    protected function configure(): void
    {
        $this
            ->setName('release:pecl')
            ->setDescription('Prepare auto-instrumentation extension for a PECL release')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'dry run')
            ->addOption('token', ['t'], InputOption::VALUE_OPTIONAL, 'github token')
            ->addOption('branch', null, InputOption::VALUE_OPTIONAL, 'branch to tag off (default: main)')
        ;
    }
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('token')) {
            $token = getenv('GITHUB_TOKEN');
            if ($token !== false) {
                $input->setOption('token', $token);
            }
        }
        if (!$input->getOption('token')) {
            throw new \RuntimeException('No github token provided (via --token or GITHUB_TOKEN env)');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->token = $input->getOption('token');
        $this->source_branch = $input->getOption('branch') ?? 'main';
        $this->dry_run = $input->getOption('dry-run');
        $this->client = HttpClientDiscovery::find();
        $this->registerInputAndOutput($input, $output);
        $project = new Project(self::REPOSITORY);
        $repository = new Repository();
        $repository->downstream = $project;
        $repository->upstream = $project;
        $repository->latestRelease = $this->get_latest_release($repository);
        $repository->commits = $this->get_downstream_unreleased_commits($repository);
        if (count($repository->commits) === 0) {
            $this->output->writeln("<info>No unreleased commits since {$repository->latestRelease->version}</info>");
            return Command::SUCCESS;
        }

        $url = sprintf("https://raw.githubusercontent.com/%s/main/package.xml", self::REPOSITORY);
        $response = $this->fetch($url);
        if ($response->getStatusCode() !== 200) {
            throw new \Exception("Error fetching {$url}");
        }

        $xml = new SimpleXMLElement($response->getBody()->getContents());

        $this->process($repository, $xml);

        return Command::SUCCESS;
    }

    private function process(Repository $repository, SimpleXMLElement $xml): void
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
        $question = new Question("<question>Latest={$prev}, enter new tag (blank to skip):</question>", null);

        $helper = new QuestionHelper();
        $newVersion = $helper->ask($this->input, $this->output, $question);
        if (!$newVersion) {
            $this->output->writeln("<info>[SKIP] not going to release {$repository->downstream}</info>");

            return;
        }

        //hack up the XML
        $release = [
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'version' => [
                'release' => $newVersion,
                'api' => '1.0',
            ],
            'notes' => "opentelemetry {$newVersion}" . PHP_EOL . $this->format_notes($repository->commits),
        ];
        $s = $this->convertPackageXml($xml, $release);
        $this->output->writeln($s);

    }

    protected function convertPackageXml(SimpleXMLElement $xml, array $release): string
    {
        $array = Xml2Array::convert($xml)->toArray();
        $package = $array['package'];
        if (!$array['package']['changelog']) {
            $array['package']['changelog'] = [];
        }
        $array['package']['changelog'][] = [
            'release' => [
                'date' => $package['date'],
                'time' => $package['time'],
                'version' => $package['version'],
                'stability' => $package['stability'],
                'license' => $package['license'],
                'notes' => $package['notes'],
            ],
        ];
        $array['package']['date'] = $release['date'];
        $array['package']['time'] = $release['time'];
        $array['package']['version'] = $release['version'];
        $array['package']['notes'] = $release['notes'];

        return Array2Xml::convert($array, ['rootElement' => null])->toXml(true);
    }

    /**
     * @param array<Commit> $commits
     * @return string
     */
    private function format_notes(array $commits): string
    {
        $notes = PHP_EOL;
        foreach ($commits as $commit) {
            //only first line of commit message
            $header = strtok($commit->message, PHP_EOL);
            $notes .= "* {$header}".PHP_EOL;
        }
        return $notes;
    }
}