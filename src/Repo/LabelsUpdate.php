<?php

namespace Ezdeliver\Repo;

readonly class LabelsUpdate
{
    /**
     * @param array<string> $labels
     */
    public function __construct(
        private int $targetId,
        private string $targetTitle,
        private array $labels,
    ) {
    }

    public function getTargetId(): int
    {
        return $this->targetId;
    }

    public function getTargetTitle(): string
    {
        return $this->targetTitle;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }
}
