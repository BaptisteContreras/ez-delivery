<?php

namespace Ezdeliver\Model;

final class Pr
{

    /**
     * @param array<Commit> $commits
     */
    public function __construct(
        private readonly int    $id,
        private readonly string $title,
        private readonly Issue  $closingIssue,
        private readonly array  $commits,
        private bool $handled = false
)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getClosingIssueId(): int
    {
        return $this->closingIssue->getId();
    }

    public function getClosingIssueTitle(): string
    {
        return $this->closingIssue->getTitle();
    }

    public function getClosingIssue(): Issue
    {
        return $this->closingIssue;
    }

    public function getCommits(): array
    {
        return $this->commits;
    }

    public function hasClosingIssueWithLabel(string $label): bool
    {
        return $this->closingIssue->hasLabel($label);
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
