<?php

namespace Ezdeliver\Repo;

readonly class IssueLabelsUpdate
{
    /**
     * @param array<string> $labels
     */
    public function __construct(
        private int $issueId,
        private string $issueTitle,
        private array $labels,
    ) {
    }

    public function getIssueId(): int
    {
        return $this->issueId;
    }

    public function getIssueTitle(): string
    {
        return $this->issueTitle;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }
}
