<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\GitlabRepoConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Repo\Converter\GitlabRawDataConverter;
use Ezdeliver\Token\TokenVault;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Castor\http_request;

class GitlabLinkedIssueDriver implements RemoteRepoDriver
{
    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly GitlabLabelResolver $labelResolver,
        private readonly TokenVault $tokenVault,
    ) {
    }

    public function support(ProjectRepoConfig $projectRepoConfig): bool
    {
        return $projectRepoConfig instanceof GitlabRepoConfig && PrSelectionMode::LinkedIssue === $projectRepoConfig->getMode();
    }

    /**
     * @param GitlabRepoConfig $projectRepoConfig
     */
    public function getPrs(ProjectRepoConfig $projectRepoConfig): array
    {
        $this->io->title('Getting data from Gitlab');

        $mrGraphqlQuery = sprintf(
            'query { 
                    project(fullPath: "%s/%s") {
                        mergeRequests(first: 200, state: opened) {
                            nodes {
                                iid
                                title
                                description
                                webUrl
                                commits(first: 100) {
                                    nodes {
                                        authorName
                                        sha
                                        message
                                        committedDate
                                    }
                                }
                            }
                        }
                      }
                    }',
            $projectRepoConfig->getNamespace(), $projectRepoConfig->getName());

        $rawMrs = json_decode(http_request('POST', 'https://gitlab.com/api/graphql', [
            'headers' => [
                'PRIVATE-TOKEN' => $this->tokenVault->get($projectRepoConfig->getApiTokenRef()),
            ],
            'json' => ['query' => $mrGraphqlQuery],
        ])->getContent(), true);

        $this->verbose(sprintf('Fetched %d merge request(s) from Gitlab', count($rawMrs['data']['project']['mergeRequests']['nodes'])));

        $issuesId = implode(',', array_map(fn (array $mr) => sprintf('"%s"', $this->extractIssueIdFromDescription($mr['description'])), $rawMrs['data']['project']['mergeRequests']['nodes']));

        $issuesGraphqlQuery = sprintf('
            query {
                project(fullPath: "%s/%s") {
                    issues(iids: [%s]) {
                        nodes {
                            iid
                            title
                            labels {
                                nodes {
                                title
                                }
                            }
                        }
                    }
                }
            }
        ',
            $projectRepoConfig->getNamespace(), $projectRepoConfig->getName(), $issuesId);

        $rawIssues = json_decode(http_request('POST', 'https://gitlab.com/api/graphql', [
            'headers' => [
                'PRIVATE-TOKEN' => $this->tokenVault->get($projectRepoConfig->getApiTokenRef()),
            ],
            'json' => ['query' => $issuesGraphqlQuery],
        ])->getContent(), true);

        $this->verbose(sprintf('Fetched %d issue(s) for iids [%s]', count($rawIssues['data']['project']['issues']['nodes']), $issuesId));

        $selectorsMap = [];

        foreach ($rawIssues['data']['project']['issues']['nodes'] as $rawIssue) {
            $selectorsMap[$rawIssue['iid']] = GitlabRawDataConverter::buildSelectorFromRawData($rawIssue);
        }

        $mrs = [];

        foreach ($rawMrs['data']['project']['mergeRequests']['nodes'] as $rawMr) {
            $selector = $selectorsMap[$this->extractIssueIdFromDescription($rawMr['description'])] ?? null;

            if (!$selector) {
                $this->verbose(sprintf('MR !%s "%s" skipped: no linkable issue found in description', $rawMr['iid'], $rawMr['title']));

                continue;
            }

            $mrs[] = GitlabRawDataConverter::buildPrFromRawData($rawMr, $selector);
        }

        return $mrs;
    }

    /**
     * @param GitlabRepoConfig    $projectRepoConfig
     * @param array<LabelsUpdate> $labelsUpdates
     */
    public function updateLabels(ProjectRepoConfig $projectRepoConfig, array $labelsUpdates): void
    {
        $this->io->title('Update Gitlab labels');

        if (empty($labelsUpdates)) {
            $this->io->info('No label to update');

            return;
        }

        $this->io->info(sprintf('Updating %s labels', count($labelsUpdates)));
        $this->io->info(implode(PHP_EOL, array_map(fn (LabelsUpdate $labelsUpdate) => sprintf('Issue #%s - %s is updated', $labelsUpdate->getTargetId(), $labelsUpdate->getTargetTitle()), $labelsUpdates)));

        $labelMap = $this->labelResolver->resolveLabelIds($projectRepoConfig);

        $mutationBody = array_map(
            fn (LabelsUpdate $labelsUpdate) => sprintf('issue%s: updateIssue(input: {projectPath: "%s/%s" iid: "%s" labelIds: [%s] }) {issue {iid  title} errors }',
                $labelsUpdate->getTargetId(),
                $projectRepoConfig->getNamespace(),
                $projectRepoConfig->getName(),
                $labelsUpdate->getTargetId(),
                implode(',', array_map(fn (string $label) => sprintf('"%s"', $labelMap[$label] ?? throw new GitlabLabelCorrespondanceNotFoundException($label)), $labelsUpdate->getLabels()))
            ), $labelsUpdates);

        $mrGraphqlQuery = sprintf('mutation { %s }', implode(' ', $mutationBody));

        $result = json_decode(http_request('POST', 'https://gitlab.com/api/graphql', [
            'headers' => [
                'PRIVATE-TOKEN' => $this->tokenVault->get($projectRepoConfig->getApiTokenRef()),
            ],
            'json' => ['query' => $mrGraphqlQuery],
        ])->getContent(), true);

        if (!empty($result['data'])) {
            foreach ($result['data'] as $issueKey => $issueData) {
                $errors = $issueData['errors'] ?? [];
                $issue = $issueData['issue'] ?? [];

                if (!empty($errors)) {
                    $iid = $issue['iid'] ?? 'unknown';
                    $title = $issue['title'] ?? 'unknown title';

                    foreach ($errors as $error) {
                        $this->io->warning(sprintf('Issue #%s (%s): %s', $iid, $title, $error));
                    }

                    continue;
                }

                $this->verbose(sprintf('Issue #%s (%s) mutation confirmed by Gitlab', $issue['iid'] ?? 'unknown', $issue['title'] ?? 'unknown title'));
            }
        }

        if (isset($result['errors'])) {
            $this->io->error(implode(PHP_EOL, array_map(fn ($error) => $error['message'], $result['errors'])));
        }

        $this->io->success('Gitlab labels updated');
    }

    /**
     * Only return the first Issue ID referenced.
     */
    private function extractIssueIdFromDescription(string $mrDescription): ?int
    {
        $matches = [];
        preg_match_all('/(?:Closes|Fixes|Resolves)?\s*#(\d+)/i', $mrDescription, $matches);

        $firstIssueId = current($matches[1]);

        return false === $firstIssueId ? null : (int) $firstIssueId;
    }

    public function supportLabelsUpdate(): bool
    {
        return true;
    }

    private function verbose(string $line): void
    {
        if ($this->io->isVerbose()) {
            $this->io->comment($line);
        }
    }
}
