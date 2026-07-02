<?php

namespace Ezdeliver\Model;

final readonly class PrReference
{
    public function __construct(
        private int $id,
        private string $title,
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
}
