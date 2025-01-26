<?php

namespace Ezdeliver\Model;

final readonly class Release
{

    /**
     * @param array<Pr> $prs
     */
    public function __construct(
        private array  $prs,
        private int    $currentPrId,
        private string $currentCommitSha,
        private string $env,
        private string $branchName
    )
    {
    }

    public function getPrs(): array
    {
        return $this->prs;
    }

    public function getCurrentPrId(): int
    {
        return $this->currentPrId;
    }

    public function getCurrentCommitSha(): int
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



    public function getConflictingPr(): Pr
    {
        return current(array_filter($this->prs, fn(Pr $pr) => $pr->getId() === $this->currentPrId));
    }

    public function getConflictingCommit(): Commit
    {
        return current(array_filter($this->getConflictingPr()->getCommits(), fn(Commit $commit) => $commit->getSha() === $this->currentCommitSha));
    }
}