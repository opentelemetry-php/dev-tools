<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Release;

use Nyholm\Psr7\Request;
use OpenTelemetry\DevTools\Console\Command\BaseCommand;
use OpenTelemetry\DevTools\Console\Release\Commit;
use OpenTelemetry\DevTools\Console\Release\PullRequest;
use OpenTelemetry\DevTools\Console\Release\Release;
use OpenTelemetry\DevTools\Console\Release\Repository;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractReleaseCommand extends BaseCommand
{
    protected ClientInterface $client;
    protected ?string $token = null;

    protected function headers(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'php-' . PHP_VERSION,
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
        if ($this->token) {
            $headers['Authorization'] ="Bearer {$this->token}";
        }
        return $headers;
    }

    protected function fetch(string $url): ResponseInterface
    {
        $request = new Request('GET', $url, $this->headers());
        $this->output->isVeryVerbose() && $this->output->writeln("[HTTP] GET {$url}");

        return $this->client->sendRequest($request);
    }

    protected function post(string $url, string $body): ResponseInterface
    {
        $request = new Request('POST', $url, $this->headers(), $body);
        $this->output->isVeryVerbose() && $this->output->writeln("[HTTP] POST {$url}");

        return $this->client->sendRequest($request);
    }

    protected function get_latest_release(Repository $repository): ?Release
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

    protected function get_downstream_unreleased_commits(Repository $repository): array
    {
        $commits_url = "https://api.github.com/repos/{$repository->downstream}/commits";
        if ($repository->latestRelease !== null) {
            $commits_url .= "?since={$repository->latestRelease->timestamp}";
        }

        return $this->get_commits($commits_url, $repository);
    }

    /**
     * @param Repository $repository
     * @return array<Commit>
     */
    protected function get_upstream_unreleased_commits(Repository $repository): array
    {
        $commits_url = "https://api.github.com/repos/{$repository->upstream}/commits?path={$repository->upstream->path}";
        if ($repository->latestRelease !== null) {
            $commits_url .= "&since={$repository->latestRelease->timestamp}";
        }

        return $this->get_commits($commits_url, $repository);
    }
    private function get_commits(string $url, Repository $repository): array
    {
        $response = $this->fetch($url);
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

    protected function get_pull_request(Repository $repository, Commit $commit): PullRequest
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

    protected function get_sha_for_branch(Repository $repository, string $branch): string
    {
        $refs_url = "https://api.github.com/repos/{$repository->upstream}/git/matching-refs/heads/{$branch}";
        $response = $this->fetch($refs_url);
        if ($response->getStatusCode() !== 200) {
            $this->output->isDebug() && $this->output->writeln($response->getBody()->getContents());
            throw new \Exception("Error {$response->getStatusCode()} retrieving branch refs for {$branch}");
        }
        $json = json_decode($response->getBody()->getContents());
        if (count($json) === 0) {
            throw new \RuntimeException("No matching refs found for branch: {$branch}");
        }
        $ref = $json[0];

        $this->output->isVerbose() && $this->output->writeln("Found ref: {$ref->ref} SHA: {$ref->object->sha}");

        return $ref->object->sha;
    }
}
