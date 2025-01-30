<?php

namespace Ezdeliver\Model;

final readonly class Issue
{
    public function __construct(
        private int    $id,
        private string $title,
        private array  $labels,
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

    public function hasLabel(string $label): bool
    {
        return in_array($label, $this->labels, true);
    }
}
