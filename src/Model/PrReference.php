<?php

namespace Ezdeliver\Model;

/*
 * @rw
 * I think you should write some doc about what is a PrReference. No need to do too much but it must be clear because this
 * concept is important
 */
final readonly class PrReference
{
    /**
     * @param array<string> $labels
     */
    public function __construct(
        private int $id,
        private string $title,
        private array $labels,
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
}
