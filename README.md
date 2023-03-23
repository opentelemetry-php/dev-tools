# Development tools for OpenTelemetry PHP

![CI Build](https://github.com/opentelemetry-php/dev-tools/workflows/PHP%20QA/badge.svg)
[![codecov](https://codecov.io/gh/opentelemetry-php/dev-tools/branch/main/graph/badge.svg?token=DSL6OW6TGC)](https://codecov.io/gh/opentelemetry-php/dev-tools)

## Release Management

A tool to find unreleased changes for OpenTelemetry, create new releases with release notes.

### Requirements

You need to be an administrator/owner of [opentelemetry-php](https://github.com/opentelemetry-php) to actually create releases. A lower-privileged account
should be able to do everything else, but will fail if you try to create a release.

You need to [create a fine-grained github access token](https://github.com/settings/personal-access-tokens/new) with:
* resource owner: `opentelemetry-php`
* repository access: `all repositories`
* permissions: `contents:read-and-write`

You can provide the token either via the `GITHUB_TOKEN` env var (preferred), or the `--token=` CLI option.

### Usage

```shell
export GITHUB_TOKEN=<fine-grain-access-token>
bin/otel release:run -[vvv] [--token=token] [--branch=main]
```

The script will then:
* fetch `.gitsplit.yaml` from source repositories
* process yaml to determine downstream (read-only) repositories and their path association in upstream (eg open-telemetry/opentelemetry-php:/src/API -> opentelemetry-php/api)
* find latest release in downstream
* find changes newer than the latest release, and their associated pull request

Once all the info has been gathered, it will iterate over each repo with unreleased changes. For each repo:
* list the changes
* display the last release version, and accept input for new version
* generate release notes
* create the release

### TODO
* probably doesn't work if we have multiple source branches (eg if we were maintaining multiple active versions, eg `1.x` and `2.x`) - need to be smarter at finding changes (eg look only on source branch)
* finding changes dated after the last release is flaky if you do weird things like recreate releases... a better way might be "find changes in branch <source> not in tag <latest>"?
