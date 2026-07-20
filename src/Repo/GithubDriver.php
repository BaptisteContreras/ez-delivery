<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\GithubRepoConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Repo\Converter\GithubRawDataConverter;
use Ezdeliver\Token\TokenVault;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Castor\http_request;

class GithubDriver implements RemoteRepoDriver
{
    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly TokenVault $tokenVault,
    ) {
    }

    public function support(ProjectRepoConfig $projectRepoConfig): bool
    {
        return $projectRepoConfig instanceof GithubRepoConfig;
    }

    /**
     * @param GithubRepoConfig $projectRepoConfig
     */
    public function getPrs(ProjectRepoConfig $projectRepoConfig): array
    {
        $this->io->title('Getting data from Github');

        $rawPrs = json_decode(http_request('POST', 'https://api.github.com/graphql', [
            'body' => sprintf('{
                    "query": "query ($owner: String!, $repo: String!, $first: Int!, $after: String) { repository(owner: $owner, name: $repo) { pullRequests(first: $first, after: $after, states: OPEN) { edges { node { id number title commits(first: 200) { edges { node { commit { oid message committedDate } } } } closingIssuesReferences(first: 1) { edges { node { id number title  labels(first: 30) { edges { node {  name } } } } } } } } pageInfo { endCursor hasNextPage } } } }",
                    "variables": {
                        "owner": "%s", 
                        "repo": "%s",    
                        "first": 100,        
                        "after": null           
                    }
                  }', $projectRepoConfig->getOwner(), $projectRepoConfig->getName()),
            'headers' => [
                'Authorization' => sprintf('bearer %s', $this->tokenVault->get($projectRepoConfig->getApiTokenRef())),
            ],
        ])->getContent(), true);

        $rawPrsWithLinkedIssue = array_filter($rawPrs['data']['repository']['pullRequests']['edges'], fn (array $pr) => isset($pr['node']['closingIssuesReferences']['edges'][0]['node']));

        $this->verbose(sprintf(
            'Fetched %d pull request(s) from Github, %d with a linked issue',
            count($rawPrs['data']['repository']['pullRequests']['edges']),
            count($rawPrsWithLinkedIssue)
        ));

        $prs = array_map(fn (array $pr) => GithubRawDataConverter::buildPrFromRawData($pr), $rawPrsWithLinkedIssue);

        return $prs;
    }

    public function supportLabelsUpdate(): bool
    {
        return false;
    }

    public function updateLabels(ProjectRepoConfig $projectRepoConfig, array $labelsUpdates): void
    {
        throw new \Exception('Not implemented');
    }

    private function verbose(string $line): void
    {
        if ($this->io->isVerbose()) {
            $this->io->comment($line);
        }
    }
}
