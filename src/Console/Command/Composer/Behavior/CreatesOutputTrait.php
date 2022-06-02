<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Console\Command\Composer\Behavior;

use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

trait CreatesOutputTrait
{
    protected function writeSingleLine(OutputInterface $output, string $message, int $options = 0): void
    {
        $output->writeln($message, $options);
    }

    protected function writeSeparator(OutputInterface $output, int $options = 0): void
    {
        $this->writeSingleLine(
            $output,
            '-------------------------------------',
            $options
        );
    }

    protected function writeBlankLine(OutputInterface $output, int $options = 0): void
    {
        $this->writeSingleLine($output, ' ', $options);
    }

    protected function writeComment(OutputInterface $output, string $message, int $options = 0): void
    {
        $this->writeSingleLine($output, '--- ' . $message, $options);
    }

    protected function writeListItem(OutputInterface $output, string $message, int $options = 0): void
    {
        $this->writeComment($output, ' - ' . $message, $options);
    }

    protected function writeHeadline(OutputInterface $output, string $message, int $options = 0): void
    {
        $this->writeSeparator($output, $options);
        $this->writeSingleLine($output, '- ' . $message, $options);
        $this->writeSeparator($output, $options);
    }

    protected function writeColoredLine(OutputInterface $output, string $message, string $color, int $options = 0): void
    {
        $this->writeSingleLine($output, "<fg=$color>$message</>", $options);
    }

    protected function writeGreenLine(OutputInterface $output, string $message, int $options = 0): void
    {
        $this->writeColoredLine($output, $message, 'green', $options);
    }

    protected function writeRedLine(OutputInterface $output, string $message, int $options = 0): void
    {
        $this->writeColoredLine($output, $message, 'green', $options);
    }

    protected function writeOk(OutputInterface $output, string $prefix = '', int $options = 0): void
    {
        $this->writeGreenLine($output, sprintf('%s%s', $prefix, 'OK!'), $options);
    }

    protected function writeError(OutputInterface $output, string $message, int $options = 0): void
    {
        $this->writeRedLine($output, "Error: $message", $options);
    }

    protected function writeThrowable(OutputInterface $output, Throwable $throwable, int $options = 0): void
    {
        $this->writeSeparator($output, $options);
        $this->writeError($output, '- ' . $throwable->getMessage(), $options);
        $this->writeSeparator($output, $options);
        $this->writeSingleLine($output, '- TRACE:', $options);
        $this->writeSingleLine($output, '- ' . $throwable->getTraceAsString(), $options);

        if ($throwable->getPrevious()) {
            $this->writeSeparator($output, $options);
            $this->writeSingleLine($output, '- PREVIOUS TRACE:', $options);
            $this->writeSingleLine($output, '- ' . $throwable->getPrevious()->getTraceAsString(), $options);
        }
        $this->writeBlankLine($output, $options);
    }
}
