<?php

namespace Ezdeliver\Model;

final class Commit
{
    private bool $handled = false;
    private bool $conflict = false;

    public function __construct(
        private readonly string            $sha,
        private readonly string            $message,
        private readonly \DateTimeImmutable $date,
    )
    {
    }

    public function getSha(): string
    {
        return $this->sha;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    public function markHandled(): void
    {
        $this->handled = true;
    }

    public function isConflict(): bool
    {
        return $this->conflict;
    }

    public function markConflicted(): void
    {
        $this->conflict = true;
    }

    public function markConflictFree(): void
    {
        $this->conflict = true;
    }
}
