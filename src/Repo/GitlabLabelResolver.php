<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\GitlabRepoConfig;
use Ezdeliver\Token\TokenVault;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Castor\http_request;

final class GitlabLabelResolver
{
    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly TokenVault $tokenVault,
    ) {
    }

    /**
     * @return array<string, string> label title => label id
     */
    public function resolveLabelIds(GitlabRepoConfig $projectRepoConfig): array
    {
        $labelListQuery = sprintf('query { project(fullPath: "%s/%s") { labels(first: 1000) { nodes { id title } } } }', $projectRepoConfig->getNamespace(), $projectRepoConfig->getName());

        $labelList = json_decode(http_request('POST', 'https://gitlab.com/api/graphql', [
            'headers' => [
                'PRIVATE-TOKEN' => $this->tokenVault->get($projectRepoConfig->getApiTokenRef()),
            ],
            'json' => ['query' => $labelListQuery],
        ])->getContent(), true);

        $labelMap = array_column($labelList['data']['project']['labels']['nodes'], 'id', 'title');

        $this->verbose(sprintf('Resolved %d label(s) from Gitlab project', count($labelMap)));

        return $labelMap;
    }

    private function verbose(string $line): void
    {
        if ($this->io->isVerbose()) {
            $this->io->comment($line);
        }
    }
}
