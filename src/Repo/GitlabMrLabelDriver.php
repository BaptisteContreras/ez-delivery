<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\GitlabRepoConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Repo\Converter\GitlabRawDataConverter;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Castor\http_request;

class GitlabMrLabelDriver implements RemoteRepoDriver
{
    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly GitlabLabelResolver $labelResolver,
    ) {
    }

    public function support(ProjectRepoConfig $projectRepoConfig): bool
    {
        return $projectRepoConfig instanceof GitlabRepoConfig && PrSelectionMode::MrLabel === $projectRepoConfig->getMode();
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
                                webUrl
                                labels {
                                    nodes {
                                        title
                                    }
                                }
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

        $this->verbose(sprintf('Fetched %d merge request(s) from Gitlab', count($rawMrs['data']['project']['mergeRequests']['nodes'])));

        return array_map(
            fn (array $rawMr) => GitlabRawDataConverter::buildPrFromRawDataWithOwnLabels($rawMr),
            $rawMrs['data']['project']['mergeRequests']['nodes']
        );
    }

    /**
     * @param GitlabRepoConfig    $projectRepoConfig
     * @param array<LabelsUpdate> $labelsUpdates
     */
    public function updateLabels(ProjectRepoConfig $projectRepoConfig, array $labelsUpdates): void
    {
        $this->io->title('Update Gitlab MR labels');

        if (empty($labelsUpdates)) {
            $this->io->info('No label to update');

            return;
        }

        $this->io->info(sprintf('Updating %s merge request(s)', count($labelsUpdates)));
        $this->io->info(implode(PHP_EOL, array_map(fn (LabelsUpdate $labelsUpdate) => sprintf('MR !%s - %s is updated', $labelsUpdate->getTargetId(), $labelsUpdate->getTargetTitle()), $labelsUpdates)));

        $labelMap = $this->labelResolver->resolveLabelIds($projectRepoConfig);

        // NOTE: mutation name/fields (mergeRequestSetLabels or similar) must be confirmed
        // against Gitlab's live GraphQL schema before this ships to production — mirrors
        // GitlabLinkedIssueDriver's "resolve label titles to IDs, then mutate" shape.
        $mutationBody = array_map(
            fn (LabelsUpdate $labelsUpdate) => sprintf('mr%s: mergeRequestSetLabels(input: {projectPath: "%s/%s" iid: "%s" labelIds: [%s] }) {mergeRequest {iid title} errors }',
                $labelsUpdate->getTargetId(),
                $projectRepoConfig->getNamespace(),
                $projectRepoConfig->getName(),
                $labelsUpdate->getTargetId(),
                implode(',', array_map(fn (string $label) => sprintf('"%s"', $labelMap[$label] ?? throw new GitlabLabelCorrespondanceNotFoundException($label)), $labelsUpdate->getLabels()))
            ), $labelsUpdates);

        $mrGraphqlQuery = sprintf('mutation { %s }', implode(' ', $mutationBody));

        $result = json_decode(http_request('POST', 'https://gitlab.com/api/graphql', [
            'headers' => [
                'PRIVATE-TOKEN' => $projectRepoConfig->getApiToken(),
            ],
            'json' => ['query' => $mrGraphqlQuery],
        ])->getContent(), true);

        if (!empty($result['data'])) {
            foreach ($result['data'] as $mrKey => $mrData) {
                $errors = $mrData['errors'] ?? [];
                $mr = $mrData['mergeRequest'] ?? [];

                if (!empty($errors)) {
                    $iid = $mr['iid'] ?? 'unknown';
                    $title = $mr['title'] ?? 'unknown title';

                    foreach ($errors as $error) {
                        $this->io->warning(sprintf('MR !%s (%s): %s', $iid, $title, $error));
                    }

                    continue;
                }

                $this->verbose(sprintf('MR !%s (%s) mutation confirmed by Gitlab', $mr['iid'] ?? 'unknown', $mr['title'] ?? 'unknown title'));
            }
        }

        if (isset($result['errors'])) {
            $this->io->error(implode(PHP_EOL, array_map(fn ($error) => $error['message'], $result['errors'])));
        }

        $this->io->success('Gitlab MR labels updated');
    }

    public function supportLabelsUpdate(): bool
    {
        return true;
    }

    public function getPrReferenceStrategy(): PrReferenceStrategy
    {
        return new NullReferenceStrategy();
    }

    public function getLabelsUpdateStrategy(): LabelsUpdateStrategy
    {
        return new GitlabMrLabelsUpdateStrategy();
    }

    private function verbose(string $line): void
    {
        if ($this->io->isVerbose()) {
            $this->io->comment($line);
        }
    }
}
