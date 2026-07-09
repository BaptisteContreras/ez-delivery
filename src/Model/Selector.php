<?php

namespace Ezdeliver\Model;

/**
 * The entity that decides whether a Pr ships, and whose labels get updated
 * after a release.
 *
 * A Pr always carries exactly one Selector. Today it is always the Pr's
 * linked issue, but the concept is deliberately not called "Issue": a
 * future mode may make the Pr its own Selector (using the Pr's own
 * labels), or back it with an external tracker (e.g. a Jira ticket).
 * Whatever backs it, a Selector is just an id, a title, and a label set.
 */
final readonly class Selector
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
