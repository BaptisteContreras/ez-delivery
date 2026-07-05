<?php

namespace Ezdeliver\Tests\Repo;

use Ezdeliver\Config\Model\GitlabRepoConfig;
use Ezdeliver\Config\Model\ProjectEnvConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;
use Ezdeliver\Repo\DriverNotFoundException;
use Ezdeliver\Repo\GithubDriver;
use Ezdeliver\Repo\GitlabLabelResolver;
use Ezdeliver\Repo\GitlabLinkedIssueDriver;
use Ezdeliver\Repo\GitlabMrLabelDriver;
use Ezdeliver\Repo\IssueReferenceStrategy;
use Ezdeliver\Repo\LabelsUpdate;
use Ezdeliver\Repo\RemoteRepo;
use Ezdeliver\Repo\RemoteRepoDriver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class RemoteRepoTest extends TestCase
{
    private function makeRemoteRepo(array $drivers, ?SymfonyStyle $io = null): RemoteRepo
    {
        return new RemoteRepo($drivers, $io ?? $this->createMock(SymfonyStyle::class));
    }

    private function makeEnv(): ProjectEnvConfig
    {
        return new ProjectEnvConfig('staging', 'delivered:staging', 'to-deliver:staging');
    }

    private function makePr(int $id, array $labels, ?PrReference $reference = null): Pr
    {
        $commit = new Commit("sha$id", 'message', new \DateTimeImmutable());

        return new Pr($id, "PR $id", $labels, $reference, [$commit]);
    }

    private function makeDriverThatSupports(array $prs = []): RemoteRepoDriver
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $driver->method('getPrs')->willReturn($prs);

        return $driver;
    }

    public function testGetPrsToDeliverIncludesPrsWithToDeliverLabel(): void
    {
        $pr = $this->makePr(1, ['to-deliver:staging']);
        $driver = $this->makeDriverThatSupports([$pr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = $this->makeRemoteRepo([$driver])->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertContains($pr, $result);
    }

    public function testGetPrsToDeliverIncludesPrsWithAlreadyDeliveredLabel(): void
    {
        $pr = $this->makePr(1, ['delivered:staging']);
        $driver = $this->makeDriverThatSupports([$pr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = $this->makeRemoteRepo([$driver])->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertContains($pr, $result);
    }

    public function testGetPrsToDeliverExcludesPrsWithoutMatchingLabel(): void
    {
        $pr = $this->makePr(1, ['bug']);
        $driver = $this->makeDriverThatSupports([$pr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = $this->makeRemoteRepo([$driver])->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertEmpty($result);
    }

    public function testGetPrsToDeliverLogsIncludedAndExcludedPrsWhenVerbose(): void
    {
        $includedPr = $this->makePr(1, ['to-deliver:staging']);
        $excludedPr = $this->makePr(2, ['bug']);
        $driver = $this->makeDriverThatSupports([$includedPr, $excludedPr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);

        $comments = [];
        $io->expects($this->exactly(2))
            ->method('comment')
            ->willReturnCallback(function ($line) use (&$comments) {
                $comments[] = $line;
            });

        $this->makeRemoteRepo([$driver], $io)->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertStringContainsString('INCLUDED', $comments[0]);
        $this->assertStringContainsString('PR #1', $comments[0]);
        $this->assertStringContainsString('excluded', $comments[1]);
        $this->assertStringContainsString('PR #2', $comments[1]);
    }

    public function testGetPrsToDeliverDoesNotLogWhenNotVerbose(): void
    {
        $pr = $this->makePr(1, ['to-deliver:staging']);
        $driver = $this->makeDriverThatSupports([$pr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(false);
        $io->expects($this->never())->method('comment');

        $this->makeRemoteRepo([$driver], $io)->getPrsToDeliver($repoConfig, $this->makeEnv());
    }

    public function testUpdateLabelsSwapsToDeliverLabelForAlreadyDeliveredUsingReferenceWhenPresent(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $pr = $this->makePr(1, ['to-deliver:staging', 'bug'], new PrReference(10, 'Issue title', []));

        $capturedUpdates = null;
        $driver->expects($this->once())
            ->method('updateLabels')
            ->willReturnCallback(function ($config, array $updates) use (&$capturedUpdates) {
                $capturedUpdates = $updates;
            });

        $this->makeRemoteRepo([$driver])->updateLabels($repoConfig, [$pr], $this->makeEnv());

        $this->assertCount(1, $capturedUpdates);
        /** @var LabelsUpdate $update */
        $update = array_values($capturedUpdates)[0];
        $this->assertSame(10, $update->getTargetId());
        $this->assertSame('Issue title', $update->getTargetTitle());
        $this->assertContains('delivered:staging', $update->getLabels());
        $this->assertNotContains('to-deliver:staging', $update->getLabels());
        $this->assertContains('bug', $update->getLabels());
    }

    public function testUpdateLabelsTargetsThePrItselfWhenNoReference(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $pr = $this->makePr(7, ['to-deliver:staging']);

        $capturedUpdates = null;
        $driver->expects($this->once())
            ->method('updateLabels')
            ->willReturnCallback(function ($config, array $updates) use (&$capturedUpdates) {
                $capturedUpdates = $updates;
            });

        $this->makeRemoteRepo([$driver])->updateLabels($repoConfig, [$pr], $this->makeEnv());

        $update = array_values($capturedUpdates)[0];
        $this->assertSame(7, $update->getTargetId());
        $this->assertSame('PR 7', $update->getTargetTitle());
    }

    public function testUpdateLabelsSkipsPrsWithoutToDeliverLabel(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $pr = $this->makePr(1, ['delivered:staging']);

        $driver->expects($this->once())
            ->method('updateLabels')
            ->with($this->anything(), $this->isEmpty());

        $this->makeRemoteRepo([$driver])->updateLabels($repoConfig, [$pr], $this->makeEnv());
    }

    public function testSupportLabelsUpdateDelegatesToDriver(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $driver->method('supportLabelsUpdate')->willReturn(true);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = $this->makeRemoteRepo([$driver])->supportLabelsUpdate($repoConfig);

        $this->assertTrue($result);
    }

    public function testGetPrReferenceStrategyDelegatesToSelectedDriver(): void
    {
        $strategy = new IssueReferenceStrategy();

        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $driver->method('getPrReferenceStrategy')->willReturn($strategy);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = $this->makeRemoteRepo([$driver])->getPrReferenceStrategy($repoConfig);

        $this->assertSame($strategy, $result);
    }

    public function testSelectDriverPicksTheDriverThatSupportsTheConfig(): void
    {
        $matchingPr = $this->makePr(1, ['to-deliver:staging']);

        $unsupportingDriver = $this->createMock(RemoteRepoDriver::class);
        $unsupportingDriver->method('support')->willReturn(false);
        $unsupportingDriver->expects($this->never())->method('getPrs');

        $supportingDriver = $this->createMock(RemoteRepoDriver::class);
        $supportingDriver->method('support')->willReturn(true);
        $supportingDriver->method('getPrs')->willReturn([$matchingPr]);

        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = $this->makeRemoteRepo([$unsupportingDriver, $supportingDriver])->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertContains($matchingPr, $result);
    }

    public function testSelectDriverThrowsWhenNoDriverSupports(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(false);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $this->expectException(DriverNotFoundException::class);

        $this->makeRemoteRepo([$driver])->getPrsToDeliver($repoConfig, $this->makeEnv());
    }

    public function testRemoteRepoSelectsGitlabLinkedIssueDriverForLinkedIssueMode(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $labelResolver = new GitlabLabelResolver($io);

        $drivers = [
            new GithubDriver($io),
            new GitlabLinkedIssueDriver($io, $labelResolver),
            new GitlabMrLabelDriver($io, $labelResolver),
        ];

        $repoConfig = new GitlabRepoConfig('ns', 'repo', 'token', PrSelectionMode::LinkedIssue);

        $strategy = $this->makeRemoteRepo($drivers)->getPrReferenceStrategy($repoConfig);

        $this->assertTrue($strategy->supportsReference());
    }

    public function testRemoteRepoSelectsGitlabMrLabelDriverForMrLabelMode(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $labelResolver = new GitlabLabelResolver($io);

        $drivers = [
            new GithubDriver($io),
            new GitlabLinkedIssueDriver($io, $labelResolver),
            new GitlabMrLabelDriver($io, $labelResolver),
        ];

        $repoConfig = new GitlabRepoConfig('ns', 'repo', 'token', PrSelectionMode::MrLabel);

        $strategy = $this->makeRemoteRepo($drivers)->getPrReferenceStrategy($repoConfig);

        $this->assertFalse($strategy->supportsReference());
    }
}
