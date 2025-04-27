<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\GitlabRepoConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Model\Issue;
use Ezdeliver\Repo\Converter\GitlabRawDataConverter;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Castor\http_request;

class GitlabDriver implements RemoteRepoDriver
{
    public function __construct(
        private readonly SymfonyStyle $io,
    ) {
    }

    public function support(ProjectRepoConfig $projectRepoConfig): bool
    {
        return $projectRepoConfig instanceof GitlabRepoConfig;
    }

    /**
     * @param GitlabRepoConfig $projectRepoConfig
     */
    public function getPrsWithLinkedIssue(ProjectRepoConfig $projectRepoConfig): array
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
                'PRIVATE-TOKEN' => $projectRepoConfig->getApiToken(),
            ],
            'json' => ['query' => $mrGraphqlQuery],
        ])->getContent(), true);

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
                'PRIVATE-TOKEN' => $projectRepoConfig->getApiToken(),
            ],
            'json' => ['query' => $issuesGraphqlQuery],
        ])->getContent(), true);

        $issuesMap = [];

        foreach ($rawIssues['data']['project']['issues']['nodes'] as $rawIssue) {
            $issuesMap[$rawIssue['iid']] = GitlabRawDataConverter::buildIssueFromRawData($rawIssue);
        }

        $mrs = [];

        foreach ($rawMrs['data']['project']['mergeRequests']['nodes'] as $rawMr) {
            $issue = $issuesMap[$this->extractIssueIdFromDescription($rawMr['description'])] ?? null;

            if (!$issue) {
                continue;
            }

            $mrs[] = GitlabRawDataConverter::buildPrFromRawData($rawMr, $issue);
        }

        return $mrs;
    }

    /**
     * @param GitlabRepoConfig $projectRepoConfig
     */
    public function updateLabels(ProjectRepoConfig $projectRepoConfig, array $issuesLabelsUpdates): void
    {
        $this->io->title('Update Gitlab labels');

        if (empty($issuesLabelsUpdates)) {
            $this->io->info('No label to update');

            return;
        }

        $this->io->info(sprintf('Updating %s labels', count($issuesLabelsUpdates)));
        $this->io->info(implode(PHP_EOL, array_map(fn (IssueLabelsUpdate $issueLabelsUpdate) => sprintf('Issue #%s - %s is updated', $issueLabelsUpdate->getIssueId(), $issueLabelsUpdate->getIssueTitle()), $issuesLabelsUpdates)));

        $labelListQuery = sprintf('query { project(fullPath: "%s/%s") { labels(first: 1000) { nodes { id title } } } }', $projectRepoConfig->getNamespace(), $projectRepoConfig->getName());

        $labelList = json_decode(http_request('POST', 'https://gitlab.com/api/graphql', [
            'headers' => [
                'PRIVATE-TOKEN' => $projectRepoConfig->getApiToken(),
            ],
            'json' => ['query' => $labelListQuery],
        ])->getContent(), true);

        $labelMap = array_column($labelList['data']['project']['labels']['nodes'], 'id', 'title');

        $mutationBody = array_map(
            fn (IssueLabelsUpdate $issueLabelsUpdate) => sprintf('issue%s: updateIssue(input: {projectPath: "%s/%s" iid: "%s" labelIds: [%s] }) {issue {iid  title} errors }',
                $issueLabelsUpdate->getIssueId(),
                $projectRepoConfig->getNamespace(),
                $projectRepoConfig->getName(),
                $issueLabelsUpdate->getIssueId(),
                implode(',', array_map(fn (string $label) => sprintf('"%s"', $labelMap[$label] ?? throw new GitlabLabelCorrespondanceNotFoundException($label)), $issueLabelsUpdate->getLabels()))
            ), $issuesLabelsUpdates);

        $mrGraphqlQuery = sprintf('mutation { %s }', implode(' ', $mutationBody));

        $result = json_decode(http_request('POST', 'https://gitlab.com/api/graphql', [
            'headers' => [
                'PRIVATE-TOKEN' => $projectRepoConfig->getApiToken(),
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
                }
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
}
