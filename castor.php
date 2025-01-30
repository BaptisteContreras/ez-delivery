<?php

use Castor\Attribute\AsContext;
use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Context;
use Ezdeliver\Factory\PackagerFactory;
use function Castor\capture;
use function Castor\fs;
use function Castor\http_request;
use function Castor\io;
use function Castor\load_dot_env;
use function Castor\run;
use function Castor\context;
use function Castor\variable;


const YES = 'yes';
const NO = 'no';

const DEFAULT_CONFIG_PATH = '~/.ez-delivery';
const CONFIG_PATH_ENV_VAR = 'EZ_DELIVERY_CONFIG_PATH';
const GITHUB_REPO = 'GITHUB';
const GITLAB_REPO = 'GITLAB';


#[AsContext(name: 'init', default: true)]
function defaultContext(): Context
{
    return (new Context())->withEnvironment([CONFIG_PATH_ENV_VAR => $_ENV[CONFIG_PATH_ENV_VAR] ?? DEFAULT_CONFIG_PATH]);
}

#[AsTask(description: 'init project config')]
function initProjectConfig(): void
{
    PackagerFactory::initFromCastorGlobalContext()
        ->createPackager()
        ->initProjectConfig();

}

function getProjectConfigPath(string $projectName, string $configPath): string
{
    return sprintf('%s/%s.json', $configPath, $projectName);
}

#[AsTask(description: 'Create a package')]
function createPackage(string $project): void
{
    exit(PackagerFactory::initFromCastorGlobalContext()
        ->createPackager()
        ->createPackage($project));

    dd('');
    $configPath = context()->environment[CONFIG_PATH_ENV_VAR];
    $config = json_decode(file_get_contents(getProjectConfigPath($project, $configPath)), true);

    $envsName = array_map(fn(array $envData) => $envData['name'], $config['envs']);
    $envSelected = io()->choice('Env to use', $envsName, current($envsName));

    $envConfig = current(array_filter($config['envs'], fn(array $envData) => $envData['name'] === $envSelected));

    $context = context()->withWorkingDirectory($config['src']);

    if (fs()->exists($config['tmpStorage'])) {
        io()->warning('Paused delivery found');

        /** @var Release $release */
        $release = unserialize(file_get_contents($config['tmpStorage']));
        $sameEnvForOldRelease = $release->getEnv() === $envConfig['name'];

        if ($sameEnvForOldRelease && YES === io()->choice('Resume paused delivery ?', [YES, NO], YES)) {
            exit(resume($release, $context, $config, $envConfig));
        }


        if (!$sameEnvForOldRelease) {
            io()->warning('Old delivery for another env, delete it...');
        }

        fs()->remove($config['tmpStorage']);
        io()->info('Removed last delivery');
    }

    $isCurrentGitStateClean = str_contains(capture('git status', context: $context), 'nothing to commit, working tree clean');

    if (!$isCurrentGitStateClean) {
        io()->error('current git state is not clean');

        exit(1);
    }

    io()->title('Getting data from Github');

    $rawPrs = json_decode(http_request('POST', 'https://api.github.com/graphql', [
        'body' => sprintf('{
                    "query": "query ($owner: String!, $repo: String!, $first: Int!, $after: String) { repository(owner: $owner, name: $repo) { pullRequests(first: $first, after: $after, states: OPEN) { edges { node { id number title commits(first: 200) { edges { node { commit { oid message committedDate } } } } closingIssuesReferences(first: 1) { edges { node { id number title  labels(first: 30) { edges { node {  name } } } } } } } } pageInfo { endCursor hasNextPage } } } }",
                    "variables": {
                        "owner": "%s", 
                        "repo": "%s",    
                        "first": 100,        
                        "after": null           
                    }
                  }', $config['repo']['owner'], $config['repo']['name']),
        'headers' => [
            'Authorization' => sprintf('bearer %s', $config['githubToken'])
        ]
    ])->getContent(), true);

    $rawPrsWithLinkedIssue = array_filter($rawPrs['data']['repository']['pullRequests']['edges'], fn(array $pr) => isset($pr['node']['closingIssuesReferences']['edges'][0]['node']));
    $prs = array_map(fn(array $pr) => Pr::buildFromRawData($pr), $rawPrsWithLinkedIssue);

    /** @var array<Pr> $prsToDeliver */
    $prsToDeliver = array_filter($prs, fn(Pr $pr) => $pr->hasClosingIssueWithLabel($envConfig['toDeliverLabel']) || $pr->hasClosingIssueWithLabel($envConfig['alreadyDeliveredLabel']));

    if (empty($prsToDeliver)) {
        io()->warning(sprintf('No PR found for env %s', $envConfig['name']));
        exit(0);
    }

    io()->title('About to deliver theses PRs');
    io()->table(['PR #ID', 'PR title', 'issue #ID', 'issue title', 'Number of commit'], array_map(fn(Pr $pr) => [$pr->getId(), $pr->getTitle(), $pr->getClosingIssueId(), $pr->getClosingIssueTitle(), $pr->getCommitsCount()], $prsToDeliver));

    if (YES !== io()->choice(sprintf('Ready to deliver these PRs for env %s ?', $envConfig['name']), [YES, NO], YES)) {
        io()->error('Delivery aborted by user');
        exit(1);
    }

    $deliveryBranchName = io()->ask('Enter delivery branch name', sprintf('%s_%s', $envConfig['name'], (new DateTimeImmutable())->format('Y-m-d_H-i-s')));
    $baseBranchName = io()->ask('Enter base branch name', $config['baseBranch']);

    io()->info(sprintf('updating %s', $baseBranchName));
    capture(sprintf('git fetch --all && git checkout %s && git pull --rebase', $baseBranchName), context: $context);
    io()->success(sprintf('%s is up to date', $baseBranchName));

    io()->info(sprintf('creating delivery branch %s from %s', $deliveryBranchName, $baseBranchName));
    capture(sprintf('git checkout -b %s', $deliveryBranchName), context: $context);
    io()->success(sprintf('%s is created', $deliveryBranchName));

    doMerge($prsToDeliver, $context, $config, $envConfig, $deliveryBranchName);

    exit(0);
}

function resume(Release $release, Context $context, array $config, array $envConfig): int
{
    $conflictingPr = $release->getConflictingPr();
    $conflictingCommit = $release->getConflictingCommit();

    io()->title('Resume delivery');
    io()->info(sprintf('Delivery paused at PR #%s "%s", commit SHA(%s) "%s"', $conflictingPr->getId(), $conflictingPr->getTitle(), $conflictingCommit->getSha(), $conflictingCommit->getMessage()));

    $currentStatus = capture('git status', context: $context);
    $mustApplyConflictResolution = str_contains($currentStatus, 'Changes to be committed');
    if ($mustApplyConflictResolution) {
        io()->title('Applying conflict resolution before resuming delivery');
        io()->info($currentStatus);

        if (YES !== io()->choice('Commit theses changes ?', [YES, NO], YES)) {
            io()->error('Conflict resolution aborted');
            return 1;

        }

        run('git cherry-pick --continue', context: $context->toInteractive());
    }

    $isCurrentGitStateClean = str_contains(capture('git status', context: $context), 'nothing to commit, working tree clean');

    if (!$isCurrentGitStateClean) {
        io()->error('current git state is not clean');

        exit(1);
    }


    doMerge($release->getPrs(), $context, $config, $envConfig, $release->getBranchName());

    fs()->remove($config['tmpStorage']);
    io()->info('Removed last delivery');

    return 0;
}

/**
 * @param array<Pr> $prsToDeliver
 */
function doMerge(array $prsToDeliver, Context $context, array $config, array $envConfig, string $branchName): void
{
    io()->progressStart(count($prsToDeliver));
    foreach ($prsToDeliver as $currentPrToDeliver) {
        if ($currentPrToDeliver->isHandled()) continue;

        io()->info(sprintf('handling PR #%s : %s', $currentPrToDeliver->getId(), $currentPrToDeliver->getTitle()));
        foreach ($currentPrToDeliver->getCommits() as $commit) {
            if ($commit->isHandled()) continue;
            io()->info(sprintf('handling commit %s : "%s"', $commit->getSha(), $commit->getMessage()));
            $commit->markHandled();
            $cherryPick = run(sprintf('git cherry-pick --allow-empty %s', $commit->getSha()), context: $context->withQuiet()->withAllowFailure());

            if ($cherryPick->isSuccessful()) {
                io()->success(sprintf('commit %s OK', $commit->getSha()));

                continue;
            }

            if (str_contains($cherryPick->getErrorOutput(), '--allow-empty')) {
                io()->warning(sprintf('commit %s is already on the delivery branch', $commit->getSha()));
                capture('git cherry-pick --skip', context: $context);

                continue;
            }

            if (str_contains($cherryPick->getOutput(), 'CONFLICT')) {
                io()->warning(sprintf('commit %s is in conflict', $commit->getSha()));
                io()->info(sprintf('current delivery state stored in %s', $config['tmpStorage']));

                $commit->markConflicted();
                fs()->dumpFile($config['tmpStorage'], serialize(new Release($prsToDeliver, $currentPrToDeliver->getId(), $commit->getSha(), $envConfig['name'], $branchName)));

                io()->warning('delivery pause here. Resolve conflict and restart command to resume delivery');

                exit(3);
            }

            io()->error(sprintf('commit %s FAIL', $commit->getSha()));

            io()->warning($cherryPick->getOutput());
            io()->warning($cherryPick->getErrorOutput());

            exit(1);
        }

        $currentPrToDeliver->markHandled();
        io()->success(sprintf('PR #%s : %s is merged', $currentPrToDeliver->getId(), $currentPrToDeliver->getTitle()));
        io()->progressAdvance();
    }

    io()->progressFinish();

    addGitReleaseInfo($prsToDeliver, $context);

    if (YES === io()->choice(sprintf('push new branch %s ?', $branchName), [YES, NO], YES)) {
        run(sprintf('git push --set-upstream origin %s', $branchName), context: $context);

        io()->success('branch pushed');
    }
}

/**
 * @param array<Pr> $prsDelivered
 */
function addGitReleaseInfo(array $prsDelivered, Context $context): void
{
    io()->info('write release info in current branch');

    $gitMessage = sprintf('AUTO RELEASE %s', PHP_EOL);
    $gitMessage .= sprintf('Number of PRs delivered : %s %s',count($prsDelivered), PHP_EOL);
    $gitMessage .= sprintf('PR #ID, Issue #ID, Issue title, Number of commit, [Commits] %s%s%s', PHP_EOL,PHP_EOL,PHP_EOL);

    foreach ($prsDelivered as $pr) {
        addPrInfo($pr, $gitMessage);
    }

    capture(sprintf('git commit --allow-empty -m "%s"', $gitMessage), context: $context);
}

function addPrInfo(Pr $pr, string &$gitMessage): void
{
    $commits = implode(';', array_map(fn(Commit $commit) => sprintf('\\"%s\\"', $commit->getSha()), $pr->getCommits()));
    $gitMessage .= sprintf('-   #%s, #%s, \"%s\", %s, [%s] %s', $pr->getId(), $pr->getClosingIssueId(), $pr->getClosingIssueTitle(), $pr->getCommitsCount(), $commits, PHP_EOL);
}



final readonly class Issue
{
    public function __construct(
        private int    $id,
        private string $title,
        private array  $labels,
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function hasLabel(string $label): bool
    {
        return in_array($label, $this->labels);
    }

    public static function buildFromRawData(array $data): self
    {
        $labelsData = !empty($data['labels']['edges']) ? $data['labels']['edges'] : [];

        return new self(
            $data['number'],
            $data['title'],
            array_map(fn(array $labelData) => $labelData['node']['name'], $labelsData)
        );
    }

}

final class Pr
{
    private bool $handled = false;

    /**
     * @param array<Commit> $commits
     */
    public function __construct(
        private readonly int    $id,
        private readonly string $title,
        private readonly Issue  $closingIssue,
        private readonly array  $commits
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getClosingIssueId(): int
    {
        return $this->closingIssue->getId();
    }

    public function getClosingIssueTitle(): string
    {
        return $this->closingIssue->getTitle();
    }

    public function getClosingIssue(): Issue
    {
        return $this->closingIssue;
    }

    public function getCommits(): array
    {
        return $this->commits;
    }

    public function hasClosingIssueWithLabel(string $label): bool
    {
        return $this->closingIssue->hasLabel($label);
    }

    public function getCommitsCount(): int
    {
        return count($this->commits);
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    public function markHandled(): void
    {
        $this->handled = true;
    }


    public static function buildFromRawData(array $data): self
    {
        $prData = $data['node'];
        return new self(
            $prData['number'],
            $prData['title'],
            Issue::buildFromRawData($prData['closingIssuesReferences']['edges'][0]['node']),
            array_map(fn(array $commitData) => Commit::buildFromRawData($commitData), $prData['commits']['edges']),
        );
    }
}

final class Commit
{
    private bool $handled = false;
    private bool $conflict = false;

    public function __construct(
        private readonly string            $sha,
        private readonly string            $message,
        private readonly DateTimeImmutable $date,
    )
    {
    }

    public function getSha(): string
    {
        return $this->sha;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    public function markHandled(): void
    {
        $this->handled = true;
    }

    public function isConflict(): bool
    {
        return $this->conflict;
    }

    public function markConflicted(): void
    {
        $this->conflict = true;
    }

    public function markConflictFree(): void
    {
        $this->conflict = true;
    }


    public static function buildFromRawData(array $data): self
    {
        $commitData = $data['node']['commit'];

        return new self(
            $commitData['oid'],
            $commitData['message'],
            new DateTimeImmutable($commitData['committedDate']),
        );
    }

}

final class Release
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
