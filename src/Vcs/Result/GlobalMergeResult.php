<?php

namespace Ezdeliver\Vcs\Result;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;

final readonly class GlobalMergeResult
{

    private function __construct(
        private MergeResultState $state,
        private ?MergeResult $problematicMergeResult = null,
        private ?Pr $problematicPr = null
    )
    {
    }

    public static function success(): self
    {
        return new self(MergeResultState::OK);
    }

    public static function error(Pr $problematicPr): self
    {
        return new self(MergeResultState::ERROR, null, $problematicPr);
    }

    public static function conflict(MergeResult $problematicMergeResult, Pr $problematicPr): self
    {
        return new self(MergeResultState::CONFLICT, $problematicMergeResult, $problematicPr);
    }

    public function getProblematicMergeResult(): MergeResult
    {
        if (!$this->isConflicting()) {
            throw new \LogicException('Should not be called on non problematic result');
        }

        return $this->problematicMergeResult;
    }

    public function getState(): MergeResultState
    {
        return $this->state;
    }

    public function getProblematicPr(): ?Pr
    {
        if (!$this->isConflicting() && !$this->isOnError()) {
            throw new \LogicException('Should not be called on non problematic result');
        }

        return $this->problematicPr;
    }

    public function getConflictingCommit(): Commit
    {
        return $this->getProblematicMergeResult()->getConflictingCommit();
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