<?php

namespace Ezdeliver\Model;

final class Pr
{
    /**
     * @param array<string> $labels
     * @param array<Commit> $commits
     */
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly array $labels,
        private readonly ?PrReference $reference,
        private readonly array $commits,
        private bool $handled = false,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return array<string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function hasLabel(string $label): bool
    {
        return in_array($label, $this->labels, true);
    }

    public function getReference(): ?PrReference
    {
        return $this->reference;
    }

    public function getCommits(): array
    {
        return $this->commits;
    }

    public function getCommitsCount(): int
    {
        return count($this->commits);
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    public function markHandled(): void
    {
        $this->handled = true;
    }
}
