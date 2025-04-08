<?php

namespace Ezdeliver\Vcs\Result;

use Symfony\Component\Process\Process;

final readonly class CherryPickResult
{
    public function __construct(
        private bool $success,
        private string $errorOutput,
        private string $output,
    ) {
    }

    public static function buildFromProcess(Process $process): self
    {
        return new self(
            $process->isSuccessful(),
            $process->getErrorOutput(),
            $process->getOutput()
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrorOutput(): string
    {
        return $this->errorOutput;
    }

    public function getOutput(): string
    {
        return $this->output;
    }
}
