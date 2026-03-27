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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PeclPrCommand extends AbstractReleaseCommand
{
    private const OWNER = 'open-telemetry';
    private const REPO = 'opentelemetry-php-instrumentation';
    private const REPOSITORY = self::OWNER . '/' . self::REPO;
    private const PACKAGE_XML_PATH = 'ext/package.xml';
    private const HEADER_PATH = 'ext/php_opentelemetry.h';

    private bool $dry_run;

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('release:pecl:pr')
            ->setDescription('Create a GitHub PR to update package.xml and php_opentelemetry.h for a PECL release')
            ->addOption('token', ['t'], InputOption::VALUE_OPTIONAL, 'github token')
            ->addOption('version', null, InputOption::VALUE_REQUIRED, 'new version (required)')
            ->addOption('stability', null, InputOption::VALUE_OPTIONAL, 'release stability: stable|beta', 'stable')
            ->addOption('base-branch', null, InputOption::VALUE_OPTIONAL, 'base branch to create PR against', 'main')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'dry run, do not make any changes')
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->token = $input->getOption('token');
        $this->dry_run = $input->getOption('dry-run');
        $this->client = Psr18ClientDiscovery::find();
        $this->registerInputAndOutput($input, $output);

        $version = $input->getOption('version');
        if (!$version) {
            $this->output->writeln('<error>--version is required</error>');

            return Command::FAILURE;
        }

        $stability = $input->getOption('stability');
        $baseBranch = $input->getOption('base-branch');
        $releaseBranch = "release/pecl-{$version}";

        $project = new Project(self::REPOSITORY);
        $repository = new Repository();
        $repository->upstream = $project;
        $repository->downstream = $project;

        // Fetch and transform package.xml
        $xmlContent = $this->fetch_and_convert_package_xml($repository, $version, $stability);
        if ($xmlContent === null) {
            return Command::FAILURE;
        }

        // Fetch and update php_opentelemetry.h
        $headerContent = $this->fetch_and_update_header($version);
        if ($headerContent === null) {
            return Command::FAILURE;
        }

        if ($this->dry_run) {
            $this->output->writeln('[DRY-RUN] Would create branch: ' . $releaseBranch);
            $this->output->writeln('[DRY-RUN] Would commit ' . self::PACKAGE_XML_PATH . ' and ' . self::HEADER_PATH);
            $this->output->writeln('[DRY-RUN] Would open PR against ' . $baseBranch);

            return Command::SUCCESS;
        }

        // Get SHA of base branch
        $baseSha = $this->get_sha_for_branch($repository, $baseBranch);

        // Create release branch
        $this->create_branch($releaseBranch, $baseSha);

        // Commit package.xml
        $this->commit_file(
            self::PACKAGE_XML_PATH,
            $xmlContent,
            "chore: update package.xml for PECL release {$version}",
            $releaseBranch
        );

        // Commit php_opentelemetry.h
        $this->commit_file(
            self::HEADER_PATH,
            $headerContent,
            "chore: update PHP_OPENTELEMETRY_VERSION to {$version}",
            $releaseBranch
        );

        // Open PR
        $prUrl = $this->create_pull_request($version, $releaseBranch, $baseBranch);
        if ($prUrl) {
            $this->output->writeln("<info>[CREATED] PR: {$prUrl}</info>");
        }

        return Command::SUCCESS;
    }

    private function fetch_and_convert_package_xml(Repository $repository, string $version, string $stability): ?string
    {
        $url = sprintf('https://raw.githubusercontent.com/%s/main/%s', self::REPOSITORY, self::PACKAGE_XML_PATH);
        $response = $this->fetch($url);
        if ($response->getStatusCode() !== 200) {
            $this->output->writeln("<error>Failed to fetch {$url}: {$response->getStatusCode()}</error>");

            return null;
        }

        $xml = new SimpleXMLElement($response->getBody()->getContents());

        $release = [
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'version' => [
                'release' => $version,
                'api' => '1.0',
            ],
            'stability' => [
                'release' => $stability,
                'api' => 'stable',
            ],
            'notes' => $this->format_notes($version),
        ];

        return $this->convert_package_xml($xml, $release);
    }

    private function convert_package_xml(SimpleXMLElement $xml, array $new): string
    {
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

        $xml->date = $new['date'];
        $xml->time = $new['time'];
        $xml->version->release = $new['version']['release'];
        $xml->version->api = $new['version']['api'];
        $xml->stability->release = $new['stability']['release'];
        $xml->stability->api = $new['stability']['api'];
        $xml->notes = $new['notes'];

        $pretty = new DOMDocument();
        $pretty->preserveWhiteSpace = false;
        $pretty->formatOutput = true;
        $pretty->loadXML($xml->saveXML());

        return $pretty->saveXML();
    }

    private function fetch_and_update_header(string $version): ?string
    {
        $url = sprintf('https://raw.githubusercontent.com/%s/main/%s', self::REPOSITORY, self::HEADER_PATH);
        $response = $this->fetch($url);
        if ($response->getStatusCode() !== 200) {
            $this->output->writeln("<error>Failed to fetch {$url}: {$response->getStatusCode()}</error>");

            return null;
        }

        $contents = $response->getBody()->getContents();
        $updated = preg_replace(
            '/(#define PHP_OPENTELEMETRY_VERSION ")[^"]+(")/m',
            '${1}' . $version . '${2}',
            $contents,
        );

        if ($updated === $contents) {
            $this->output->writeln('<comment>[WARN] PHP_OPENTELEMETRY_VERSION define not found in header file</comment>');
        }

        return $updated;
    }

    private function get_file_sha(string $path): string
    {
        $url = "https://api.github.com/repos/" . self::REPOSITORY . "/contents/{$path}";
        $response = $this->fetch($url);
        if ($response->getStatusCode() !== 200) {
            throw new Exception("Failed to get file SHA for {$path}: {$response->getStatusCode()}");
        }
        $data = json_decode($response->getBody()->getContents());

        return $data->sha;
    }

    private function create_branch(string $branch, string $sha): void
    {
        $url = "https://api.github.com/repos/" . self::REPOSITORY . "/git/refs";
        $body = json_encode([
            'ref' => "refs/heads/{$branch}",
            'sha' => $sha,
        ]);
        $response = $this->post($url, $body);
        if ($response->getStatusCode() !== 201) {
            throw new Exception("Failed to create branch {$branch}: {$response->getStatusCode()} {$response->getBody()->getContents()}");
        }
        $this->output->writeln("<info>[CREATED] branch: {$branch}</info>");
    }

    private function commit_file(string $path, string $content, string $message, string $branch): void
    {
        $currentSha = $this->get_file_sha($path);
        $url = "https://api.github.com/repos/" . self::REPOSITORY . "/contents/{$path}";
        $body = json_encode([
            'message' => $message,
            'content' => base64_encode($content),
            'sha' => $currentSha,
            'branch' => $branch,
        ]);
        $response = $this->put($url, $body);
        if ($response->getStatusCode() !== 200) {
            throw new Exception("Failed to commit {$path}: {$response->getStatusCode()} {$response->getBody()->getContents()}");
        }
        $this->output->writeln("<info>[COMMITTED] {$path}</info>");
    }

    private function create_pull_request(string $version, string $head, string $base): ?string
    {
        $url = "https://api.github.com/repos/" . self::REPOSITORY . "/pulls";
        $body = json_encode([
            'title' => "chore: PECL release {$version}",
            'body' => "Automated PR to update `package.xml` and `php_opentelemetry.h` for PECL release {$version}.\n\nSee https://github.com/" . self::REPOSITORY . "/releases/tag/{$version}",
            'head' => $head,
            'base' => $base,
        ]);
        $response = $this->post($url, $body);
        if ($response->getStatusCode() !== 201) {
            $this->output->writeln("<error>Failed to create PR: {$response->getStatusCode()} {$response->getBody()->getContents()}</error>");

            return null;
        }
        $data = json_decode($response->getBody()->getContents());

        return $data->html_url;
    }

    private function format_notes(string $version): string
    {
        return sprintf('See https://github.com/%s/%s/releases/tag/%s', self::OWNER, self::REPO, $version);
    }
}
