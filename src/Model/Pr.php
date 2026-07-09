<?php

namespace Ezdeliver\Model;

final class Pr
{
    /**
     * @param array<Commit> $commits
     */
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly Selector $selector,
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

    public function getSelector(): Selector
    {
        return $this->selector;
    }

    public function getSelectorId(): int
    {
        return $this->selector->getId();
    }

    public function getSelectorTitle(): string
    {
        return $this->selector->getTitle();
    }

    /**
     * @return array<string>
     */
    public function getSelectorLabels(): array
    {
        return $this->selector->getLabels();
    }

    /**
     * @return array<Commit>
     */
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
