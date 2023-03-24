<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Release;

use Http\Discovery\HttpClientDiscovery;
use Nyholm\Psr7\Request;
use OpenTelemetry\DevTools\Console\Command\BaseCommand;
use OpenTelemetry\DevTools\Console\Release\Commit;
use OpenTelemetry\DevTools\Console\Release\Diff;
use OpenTelemetry\DevTools\Console\Release\Project;
use OpenTelemetry\DevTools\Console\Release\PullRequest;
use OpenTelemetry\DevTools\Console\Release\Release;
use OpenTelemetry\DevTools\Console\Release\Repository;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Parser;

class ReleaseCommand extends BaseCommand
{
    private const AVAILABLE_REPOS = [
        'core'    => 'open-telemetry/opentelemetry-php',
        'contrib' => 'open-telemetry/opentelemetry-php-contrib',
    ];
    private array $sources = [];
    private ClientInterface $client;
    private Parser $parser;
    private string $token;
    private string $source_branch;
    private bool $dry_run;

    protected function configure(): void
    {
        $this
            ->setName('release:run')
            ->setDescription('Find unreleased changes and create a new release')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'dry run')
            ->addOption('token', ['t'], InputOption::VALUE_OPTIONAL, 'github token')
            ->addOption('branch', null, InputOption::VALUE_OPTIONAL, 'branch to tag off (default: main)')
            ->addOption('repo', ['r'], InputOption::VALUE_OPTIONAL, 'repo to handle (core, contrib)')
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
        $source = $input->getOption('repo');
        if ($source && !array_key_exists($source, self::AVAILABLE_REPOS)) {
            $options = implode(',', array_keys(self::AVAILABLE_REPOS));
            $this->output->writeln("<error>Invalid source: {$source}. Options: {$options}</error>");
        }
        $this->sources = $source ? [$source => self::AVAILABLE_REPOS[$source]] : self::AVAILABLE_REPOS;
        $this->client = HttpClientDiscovery::find();
        $this->parser = new Parser();
        $this->registerInputAndOutput($input, $output);

        $repositories = [];

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
        foreach ($found as $rep) {
            $repository = $this->populate_release_details($rep);

            if ($this->compare_diffs_to_unreleased($repository) === false) {
                $this->output->writeln("[SKIP] Skipping {$repository->downstream} due to differences");
            } else {
                $repositories[] = $repository;
            }
        }
        $this->publish_repositories($repositories);

        return Command::SUCCESS;
    }

    /**
     * @throws \Exception
     * @return array<Repository>
     */
    private function find_repositories(): array
    {
        $repositories = [];
        foreach ($this->sources as $key => $repo) {
            $this->output->isVerbose() && $this->output->writeln("<info>Fetching .gitsplit.yaml for {$key} ({$repo})</info>");
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
            $repository->upstream = new Project($repo);
            $repository->upstream->path = $entry['prefix'];
            $target = $entry['target'];
            $repository->downstream = new Project(str_replace(['https://${GH_TOKEN}@github.com/', '.git'], ['',''], $target));
            $repositories[] = $repository;
        }

        return $repositories;
    }

    /**
     * For a given repository, populate:
     * 1. latest release
     * 2. unreleased commits to upstream path, since date of last release
     * 3. diffs between latest tag in downstream and selected branch (eg main)
     */
    private function populate_release_details(Repository $repository): Repository
    {
        $this->output->isVerbose() && $this->output->writeln("<info>Processing: {$repository->downstream}</info>");

        $repository->latestRelease = $this->get_latest_release($repository);
        $repository->commits = $this->get_unreleased_commits($repository);
        $repository->diff = $this->get_diffs($repository);

        return $repository;
    }

    /**
     * Compare downstream diffs to upstream changes found by date.
     * safety check that the changes found in upstream match diffs (which should be more accurate, but can't be used as the
     * primary method of matching because most commit detains are changed during git-split
     * We can only compare based on commit message vs PR message
     */
    private function compare_diffs_to_unreleased(Repository $repository): bool
    {
        if ($repository->latestRelease === null) {
            //initial release, nothing to do
            return true;
        }
        if ($repository->diff->commits === [] && $repository->commits === []) {
            //nothing to do
            return true;
        }
        $diffCommits = $foundCommits = [];
        foreach ($repository->diff->commits as $commit) {
            $diffCommits[] = $commit->message;
        }
        foreach ($repository->commits as $commit) {
            $foundCommits[] = $commit->message;
        }
        $this->output->isDebug() && $this->output->writeln('[COMPARE] ' . json_encode($diffCommits) . ' with ' . json_encode($foundCommits));

        $differences = array_diff($diffCommits, $foundCommits);
        if (count($differences) !== 0) {
            $this->output->writeln('<error>Downstream compare contains commit differences to upstream search</error>');

            return false;
        }

        return true;
    }

    private function get_diffs(Repository $repository): Diff
    {
        $diff = new Diff();
        if (!$repository->latestRelease) {
            //nothing to compare against
            return $diff;
        }
        $url = "https://api.github.com/repos/{$repository->downstream}/compare/{$repository->latestRelease->version}...{$this->source_branch}";
        $response = $this->fetch($url);
        if ($response->getStatusCode() !== 200) {
            $this->output->writeln("<error>Failed to compare latest release with {$this->source_branch}</error>");

            return $diff;
        }
        $data = json_decode($response->getBody()->getContents());

        foreach ($data->commits as $c) {
            $commit = new Commit();
            $commit->sha = $c->sha;
            $commit->message = $c->commit->message;
            $diff->commits[] = $commit;
        }

        return $diff;
    }

    /**
     * @param Repository $repository
     * @return array<Commit>
     */
    private function get_unreleased_commits(Repository $repository): array
    {
        $commits_url = "https://api.github.com/repos/{$repository->upstream}/commits?path={$repository->upstream->path}";
        if ($repository->latestRelease !== null) {
            $commits_url .= "&since={$repository->latestRelease->timestamp}";
        }
        $response = $this->fetch($commits_url);
        $data = json_decode($response->getBody()->getContents());
        $commits = [];
        foreach ($data as $row) {
            $commit = new Commit();
            $commit->sha = $row->sha;
            $commit->message = $row->commit->message;
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
        $release_url = "https://api.github.com/repos/{$repository->downstream}/releases/latest";

        $response = $this->fetch($release_url);
        if ($response->getStatusCode() === 404) {
            $this->output->writeln('<error>No latest release found</error>');

            return null;
        }
        if ($response->getStatusCode() !== 200) {
            $this->output->writeln("<error>({$response->getStatusCode()}) {$response->getBody()}</error>");

            throw new \Exception("Error retrieving latest release for {$repository->downstream}: " . $response->getReasonPhrase(), $response->getStatusCode());
        }

        $data = json_decode($response->getBody()->getContents());

        $release = new Release();
        $release->timestamp = $data->published_at;
        $release->version = $data->tag_name;
        $this->output->isVerbose() && $this->output->writeln("[INFO] Latest release of {$repository->downstream} is {$release}");

        return $release;
    }

    /**
     * @param array<Repository> $repositories
     * @return void
     */
    private function publish_repositories(array $repositories): void
    {
        foreach ($repositories as $repo) {
            if (count($repo->commits) === 0) {
                $this->output->isVerbose() && $this->output->writeln("<info>[SKIP] {$repo->downstream} (no new commits)</info>");

                continue;
            }
            $this->handle_unreleased($repo);
        }
    }

    /**
     * @phan-suppress PhanUndeclaredMethod
     * @psalm-suppress UndefinedInterfaceMethod
     */
    private function handle_unreleased(Repository $repository): void
    {
        $release = new Release();
        $cnt = count($repository->commits);
        $this->output->writeln("<info>[{$repository->downstream}]</info> {$cnt} unreleased change(s):");
        foreach ($repository->commits as $commit) {
            $this->output->writeln("<comment>* {$commit->pullRequest->title} ({$commit->pullRequest->author})</comment>");
        }

        $prev = ($repository->latestRelease === null)
            ? '-nothing-'
            : $repository->latestRelease->version;
        $question = new Question("<question>Latest={$prev}, enter new tag (blank to skip):</question>", null);

        $helper = $this->getHelper('question');
        /** @phpstan-ignore-next-line */
        $newVersion = $helper->ask($this->input, $this->output, $question);
        if (!$newVersion) {
            $this->output->writeln("<info>[SKIP] not going to release {$repository->downstream}</info>");

            return;
        }
        $release->version = $newVersion;
        $question = new ConfirmationQuestion('<question>Make this the latest release (Y/n)?</question>', true);
        /** @phpstan-ignore-next-line */
        $makeLatest = $helper->ask($this->input, $this->output, $question);
        $notes = [];
        if ($repository->latestRelease === null) {
            $notes[] = 'Initial release';
        } else {
            $notes[] = "What's Changed:";
            foreach ($repository->commits as $commit) {
                $notes[] = "* {$commit->pullRequest->title} by @{$commit->pullRequest->author} in [{$commit->pullRequest->id}]({$commit->pullRequest->url})";
            }
            $notes[] = '';
            $notes[] = "**Full Changelog**: https://github.com/{$repository->downstream}/compare/{$repository->latestRelease->version}...{$release->version}";
        }
        $release->notes = implode(PHP_EOL, $notes);

        $this->do_release($repository, $release, $makeLatest);
    }

    private function fetch(string $url): ResponseInterface
    {
        $request = new Request('GET', $url, [
            'Authorization' => "token {$this->token}",
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'php',
        ]);
        $this->output->isVeryVerbose() && $this->output->writeln("[HTTP] {$request->getMethod()} ${url}");

        return $this->client->sendRequest($request);
    }

    private function do_release(Repository $repository, Release $release, bool $makeLatest)
    {
        $url = "https://api.github.com/repos/{$repository->downstream}/releases";
        $body = json_encode([
            'tag_name' => $release->version,
            'target_commitish' => $this->source_branch,
            'name' => "Release {$release->version}",
            'body' => $release->notes,
            'draft' => false,
            'prerelease' => false,
            'generate_release_notes' => false,
            'make_latest' => $makeLatest ? 'true' : 'false',
        ]);
        $request = new Request('POST', $url, [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => "Bearer {$this->token}",
            'User-Agent' => 'php-' . PHP_VERSION,
            'X-GitHub-Api-Version' => '2022-11-28',
        ], $body);
        $this->output->isDebug() && $this->output->writeln("$body");
        if ($this->dry_run) {
            $this->output->writeln("[DRY-RUN] {$url}");

            return;
        }
        $response = $this->client->sendRequest($request);
        if ($response->getStatusCode() !== 201) {
            $this->output->writeln("<error>[ERROR] ({$response->getStatusCode()}) {$response->getBody()->getContents()}</error>");
        } else {
            $json = json_decode($response->getBody()->getContents());
            $this->output->writeln("<info>[CREATED] {$repository->downstream} {$release->version}: </info> {$json->html_url}");
        }
    }
}
