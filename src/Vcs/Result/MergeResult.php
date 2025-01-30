<?php

namespace Ezdeliver\Vcs\Result;

use Ezdeliver\Model\Commit;

final readonly class MergeResult
{
    private function __construct(
        private MergeResultState $state,
        private ?Commit $conflictingCommit = null
    )
    {
    }

    public static function success(): self
    {
        return new self(MergeResultState::OK);
    }

    public static function error(): self
    {
        return new self(MergeResultState::ERROR);
    }

    public static function conflict(Commit $conflictingCommit): self
    {
        return new self(MergeResultState::CONFLICT, $conflictingCommit);
    }

    public function getState(): MergeResultState
    {
        return $this->state;
    }

    public function getConflictingCommit(): Commit
    {
        if (!$this->isConflicting()) {
            throw new \LogicException('Should not be called on non conflicting result');
        }

        return $this->conflictingCommit;
    }

    public function isSuccess(): bool
    {
        return MergeResultState::OK === $this->state;
    }

    public function isOnError(): bool
    {
        return MergeResultState::ERROR === $this->state;
    }

    public function isConflicting(): bool
    {
        return MergeResultState::CONFLICT === $this->state;
    }


}