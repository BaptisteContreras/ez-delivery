<?php

namespace Ezdeliver\Model;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final class Commit
{
    public function __construct(
        private readonly string $sha,
        private readonly string $message,
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s'])]
        private readonly \DateTimeImmutable $date,

        private bool $handled = false,
        private bool $conflict = false,
    ) {
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
