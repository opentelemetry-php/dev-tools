<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Release;

use Http\Discovery\HttpClientDiscovery;
use Nyholm\Psr7\Request;
use OpenTelemetry\DevTools\Console\Command\BaseCommand;
use OpenTelemetry\DevTools\Console\Release\Commit;
use OpenTelemetry\DevTools\Console\Release\PullRequest;
use OpenTelemetry\DevTools\Console\Release\Release;
use OpenTelemetry\DevTools\Console\Release\Repository;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Parser;

class ReleaseCommand extends BaseCommand
{
    //otel monorepos, containing a top-level .gitsplit.yaml file
    private array $sources = [
        'core' => 'open-telemetry/opentelemetry-php',
        'contrib' => 'open-telemetry/opentelemetry-php-contrib',
    ];
    private ClientInterface $client;
    private Parser $parser;
    private string $token;

    protected function configure(): void
    {
        $this
            ->setName('release:run')
            ->setDescription('Find unreleased changes and create a release + notes')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'dry run')
            ->addOption('token', ['t'], InputOption::VALUE_OPTIONAL, 'github token')
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
        $this->client = HttpClientDiscovery::find();
        $this->parser = new Parser();
        $this->registerInputAndOutput($input, $output);

        $repositories = [];
        foreach ($this->find() as $repository) {
            $repositories[] = $this->process($repository);
        }
        $this->finish($repositories);

        return Command::SUCCESS;
    }

    /**
     * @throws \Exception
     * @return array<Repository>
     */
    private function find(): array
    {
        $repositories = [];
        foreach ($this->sources as $key => $repo) {
            $this->output->isVerbose() && $this->output->writeln("<info>Fetching .gitsplit.yaml for {$key}</info>");
            $repositories += $this->get_gitsplit_repositories($repo);
        }

        return $repositories;
    }

    private function get_gitsplit_repositories(string $repo): array
    {
        $url = "https://raw.githubusercontent.com/{$repo}/main/.gitsplit.yml";
        $response = $this->fetch($url);
        if ($response->getStatusCode() !== 200) {
            throw new \Exception("Error fetching {$url}");
        }

        $yaml = $this->parser->parse($response->getBody()->getContents());
        $repositories = [];
        foreach ($yaml['splits'] as $entry) {
            $repository = new Repository();
            $repository->upstream = $repo;
            $repository->path = $entry['prefix'];
            $repository->downstream = $entry['target'];
            $repositories[] = $repository;
        }

        return $repositories;
    }
    private function process(Repository $repository): Repository
    {
        $parts = explode('/', str_replace(['https://${GH_TOKEN}@github.com/', '.git'], ['',''], $repository->downstream));
        assert(count($parts) === 2);
        $org = $parts[0];
        $repo = $parts[1];
        $repository->org = $org;
        $repository->downstream = $repo;
        $this->output->isVerbose() && $this->output->writeln("<info>Processing: {$org}/{$repo}</info>");

        $repository->latestRelease = $this->get_latest_release($repository);
        foreach ($this->get_unreleased_commits($repository) as $commit) {
            $repository->commits[] = $commit;
        }

        return $repository;
    }

    /**
     * @param Repository $repository
     * @return array<Commit>
     */
    private function get_unreleased_commits(Repository $repository): array
    {
        $commits_url = "https://api.github.com/repos/{$repository->upstream}/commits?path={$repository->path}";
        if ($repository->latestRelease !== null) {
            $commits_url .= "&since={$repository->latestRelease->timestamp}";
        }
        $response = $this->fetch($commits_url);
        $data = json_decode($response->getBody()->getContents());
        $commits = [];
        foreach ($data as $row) {
            $commit = new Commit();
            $commit->sha = $row->sha;
            $commit->pullRequest = $this->get_pull_request($repository, $commit);
            $commits[] = $commit;
        }

        return $commits;
    }

    public function get_pull_request(Repository $repository, Commit $commit): PullRequest
    {
        $prs_url = "https://api.github.com/repos/{$repository->upstream}/commits/{$commit->sha}/pulls";
        $response = $this->fetch($prs_url);
        if ($response->getStatusCode() === 404) {
            //repo settings should mean this is not possible
            throw new \RuntimeException("Pull request not found for commit SHA {$commit->sha}");
        }
        if ($response->getStatusCode() !== 200) {
            $this->output->isDebug() && $this->output->writeln($response->getBody()->getContents());

            throw new \Exception('Error retrieving pull request');
        }

        $json = json_decode($response->getBody()->getContents());
        if (count($json) === 0) {
            throw new \RuntimeException("Pull request not found for commit SHA {$commit->sha}");
        }
        if (count($json) > 1) {
            $this->output->writeln("[WARN] multiple PRs for commit {$commit->sha}, choosing first...");
        }
        $row = $json[0];
        $pr = new PullRequest();
        $pr->author = $row->user->login;
        $pr->url = $row->html_url;
        $pr->id = $row->number;
        $pr->title = $row->title;

        return $pr;
    }

    private function get_latest_release(Repository $repository): ?Release
    {
        $release_url = "https://api.github.com/repos/{$repository->org}/{$repository->downstream}/releases/latest";

        $response = $this->fetch($release_url);
        if ($response->getStatusCode() === 404) {
            $this->output->writeln('<error>No latest release found</error>');

            return null;
        }
        if ($response->getStatusCode() !== 200) {
            $this->output->writeln("<error>({$response->getStatusCode()}) {$response->getBody()}</error>");

            throw new \Exception("Error retrieving latest release for {$repository->org}/{$repository->downstream}: " . $response->getReasonPhrase(), $response->getStatusCode());
        }

        $data = json_decode($response->getBody()->getContents());

        $release = new Release();
        $release->timestamp = $data->published_at;
        $release->version = $data->tag_name;

        return $release;
    }

    /**
     * @param array<Repository> $repositories
     * @return void
     */
    private function finish(array $repositories): void
    {
        foreach ($repositories as $repo) {
            if (count($repo->commits) === 0) {
                $this->output->isVerbose() && $this->output->writeln("<info>[SKIP] {$repo->org}/{$repo->downstream} (no new commits)</info>");
            } else {
                $this->handle_unreleased($repo);
            }
        }
    }

    private function handle_unreleased(Repository $repository): void
    {
        $release = new Release();
        $cnt = count($repository->commits);
        $this->output->writeln("<info>[{$repository->downstream}]</info> There are {$cnt} unreleased change(s):");
        foreach ($repository->commits as $i => $commit) {
            $this->output->writeln("<comment>{$i} - {$commit->pullRequest->title} ({$commit->pullRequest->author})</comment>");
        }
        $helper = $this->getHelper('question');
        $prev = ($repository->latestRelease === null)
            ? '-nothing-'
            : $repository->latestRelease->version;
        $question = new Question("<question>Latest={$prev}, enter new tag (blank to skip):</question>", null);

        $newVersion = $helper->ask($this->input, $this->output, $question);
        if (!$newVersion) {
            $this->output->writeln("<info>[SKIP] not going to release {$repository->downstream}</info>");

            return;
        }
        $release->version = $newVersion;
        $notes = [];
        if ($repository->latestRelease === null) {
            $notes[] = 'Initial release';
        } else {
            $notes[] = "What's Changed:";
            foreach ($repository->commits as $commit) {
                $notes[] = "* {$commit->pullRequest->title} by @{$commit->pullRequest->author} in [{$commit->pullRequest->id}]({$commit->pullRequest->url})";
            }
            $notes[] = '';
            $notes[] = "**Full Changelog**: https://github.com/{$repository->org}/{$repository->downstream}/compare/{$repository->latestRelease->version}...{$release->version}";
        }
        $release->notes = implode(PHP_EOL, $notes);

        $this->do_release($repository, $release);
    }

    private function fetch(string $url): ResponseInterface
    {
        $this->output->isVeryVerbose() && $this->output->writeln("[HTTP] ${url}");
        $request = new Request('GET', $url, [
            'Authorization' => "token {$this->token}",
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'php',
        ]);

        return $this->client->sendRequest($request);
    }

    private function do_release(Repository $repository, Release $release)
    {
        $url = "https://api.github.com/repos/{$repository->org}/{$repository->downstream}/releases";
        $body = json_encode([
            'tag_name' => $release->version,
            'target_commitish' => 'main',
            'name' => "Release {$release->version}",
            'body' => $release->notes,
            'draft' => false,
            'prerelease' => false,
            'generate_release_notes' => false,
        ]);
        $request = new Request('POST', $url, [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => "Bearer {$this->token}",
            'User-Agent' => 'php-' . PHP_VERSION,
            'X-GitHub-Api-Version' => '2022-11-28',
        ], $body);
        $this->output->isDebug() && $this->output->writeln("<info>$body</info>");
        $response = $this->client->sendRequest($request);
        if ($response->getStatusCode() !== 201) {
            $this->output->writeln("<error>[ERROR] ({$response->getStatusCode()}) {$response->getBody()->getContents()}</error>");
        } else {
            $json = json_decode($response->getBody()->getContents());
            $this->output->writeln("<info>[CREATED] {$repository->downstream} {$release->version}: </info> {$json->html_url}");
        }
    }
}
