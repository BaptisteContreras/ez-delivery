<?php

namespace Ezdeliver\Factory;

use Castor\Context;
use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Vcs\CherryPickMergeStrategy;
use Ezdeliver\Vcs\GitDriver;
use Ezdeliver\Vcs\GitWorkspace;
use Symfony\Component\Console\Style\SymfonyStyle;

class GitWorkspaceFactory
{
    private ?CherryPickMergeStrategy $cherryPickMergeStrategy = null;

    public function __construct(
        private readonly GitDriver $gitDriver,
        private readonly SymfonyStyle $io,
        private readonly PrReleaseInfoFormatterFactory $prReleaseInfoFormatterFactory,
    ) {
    }

    public function createWorkspaceWithCherryPickMergeStrategy(Context $context, PrSelectionMode $mode): GitWorkspace
    {
        return new GitWorkspace(
            $this->gitDriver,
            $context,
            $this->createCherryPickMergeStrategy(),
            $this->prReleaseInfoFormatterFactory->create($mode),
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
