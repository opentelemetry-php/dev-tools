<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Release;

class ConventionalCommitVersionDetector
{
    private const BREAKING_FOOTER_PATTERN = '/^BREAKING[- ]CHANGE:/m';
    private const BREAKING_BANG_PATTERN = '/^[a-z]+(\([^)]+\))?!:/';
    private const MINOR_PATTERN = '/^feat(\([^)]+\))?:/';

    public const BUMP_MAJOR = 'major';
    public const BUMP_MINOR = 'minor';
    public const BUMP_PATCH = 'patch';

    private const BUMP_ORDER = [
        self::BUMP_PATCH => 0,
        self::BUMP_MINOR => 1,
        self::BUMP_MAJOR => 2,
    ];

    /**
     * @param array<Commit> $commits
     */
    public function detect(array $commits): string
    {
        $highest = self::BUMP_PATCH;
        foreach ($commits as $commit) {
            $detected = $this->detectForMessage($commit->message);
            if (self::BUMP_ORDER[$detected] > self::BUMP_ORDER[$highest]) {
                $highest = $detected;
            }
            if ($highest === self::BUMP_MAJOR) {
                break;
            }
        }

        return $highest;
    }

    public function detectForMessage(string $message): string
    {
        if (preg_match(self::BREAKING_FOOTER_PATTERN, $message)) {
            return self::BUMP_MAJOR;
        }
        $firstLine = strtok($message, "\n");
        if ($firstLine !== false) {
            if (preg_match(self::BREAKING_BANG_PATTERN, $firstLine)) {
                return self::BUMP_MAJOR;
            }
            if (preg_match(self::MINOR_PATTERN, $firstLine)) {
                return self::BUMP_MINOR;
            }
        }

        return self::BUMP_PATCH;
    }
}
