<?php

namespace Ezdeliver\Factory;

use Castor\Context;
use Ezdeliver\Vcs\CherryPickMergeStrategy;
use Ezdeliver\Vcs\GitDriver;
use Ezdeliver\Vcs\GitWorkspace;
use Symfony\Component\Console\Style\SymfonyStyle;

class GitWorkspaceFactory
{

    private ?CherryPickMergeStrategy $cherryPickMergeStrategy = null;

    public function __construct(
        private readonly GitDriver $gitDriver,
        private readonly SymfonyStyle $io
    ){
    }

    public function createWorkspaceWithCherryPickMergeStrategy(Context $context): GitWorkspace
    {
        return new GitWorkspace(
            $this->gitDriver,
            $context,
            $this->createCherryPickMergeStrategy(),
            $this->io
        );
    }

    private function createCherryPickMergeStrategy(): CherryPickMergeStrategy
    {
        return $this->cherryPickMergeStrategy ??= new CherryPickMergeStrategy(
            $this->gitDriver,
            $this->io
        );
    }
}