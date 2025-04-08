<?php

namespace Ezdeliver\Model;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

readonly class Release
{
    /**
     * @param array<Pr> $prs
     */
    public function __construct(
        private array $prs,
        private int $currentPrId,
        private string $currentCommitSha,
        private string $env,
        private string $branchName,
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s'])]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }

    /**
     * @return array<Pr>
     */
    public function getPrs(): array
    {
        return $this->prs;
    }

    public function getCurrentPrId(): int
    {
        return $this->currentPrId;
    }

    public function getCurrentCommitSha(): string
    {
        return $this->currentCommitSha;
    }

    public function getEnv(): string
    {
        return $this->env;
    }

    public function getBranchName(): string
    {
        return $this->branchName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[Ignore]
    public function getConflictingPr(): Pr
    {
        return current(array_filter($this->prs, fn (Pr $pr) => $pr->getId() === $this->currentPrId));
    }

    #[Ignore]
    public function getConflictingCommit(): Commit
    {
        return current(array_filter($this->getConflictingPr()->getCommits(), fn (Commit $commit) => $commit->getSha() === $this->currentCommitSha));
    }
}
