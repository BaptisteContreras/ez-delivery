<?php

namespace Ezdeliver\Config;

use Ezdeliver\Config\Model\GithubRepoConfig;
use Ezdeliver\Config\Model\GitlabRepoConfig;
use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\Model\ProjectEnvConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Const\Interactive;
use Symfony\Component\Console\Style\SymfonyStyle;

class InteractiveBuilder
{


    public function __construct(
        private readonly SymfonyStyle   $io,
        private readonly StorageHandler $storageHandler,
    )
    {
    }

    public function buildConfig(): ProjectConfiguration
    {
        return new ProjectConfiguration(
            projectName: $this->getProjectName(),
            src: $this->getRequiredValue('Project source path'),
            baseBranch: $this->getRequiredValue('Base branch to create a package'),
            repo: $this->getRepoConfig(
                $this->io->choice(
                    'What kind of repo ? ',
                    [GithubRepoConfig::TYPE, GitlabRepoConfig::TYPE],
                    GitlabRepoConfig::TYPE)
            ),
            envs: $this->getEnvs()
        );
    }

    private function getProjectName(): string
    {
        return $this->io->ask('Project name', validator: function ($value) {
            if (empty($value)) {
                throw new \Exception('Project name cannot be empty');
            }

            if ($this->storageHandler->isProjectConfigExists($value)) {
                throw new \Exception(sprintf('project %s config already exists', $value));
            }

            return $value;
        });
    }

    private function getRequiredValue(string $valueName)
    {
        return $this->io->ask($valueName, validator: function ($value) use ($valueName) {
            if (empty($value)) {
                throw new \Exception(sprintf('%s cannot be empty', $valueName));
            }


            return $value;
        });
    }

    private function getRepoConfig(string $repoType): ProjectRepoConfig
    {
        return match ($repoType) {
            GitlabRepoConfig::TYPE => new GitlabRepoConfig(),
            GithubRepoConfig::TYPE => new GithubRepoConfig(
                owner: $this->getRequiredValue('Repository owner'),
                name: $this->getRequiredValue('Repository name'),
                apiToken: $this->getRequiredValue('Github personal token'),
            ),
        };
    }

    /**
     * @return array<ProjectEnvConfig>
     */
    private function getEnvs(): array
    {
        $envs = [];
        do {
            $this->io->title('Add new environnement');

            $newEnv = new ProjectEnvConfig(
                name: $this->io->ask('env name', validator: function ($value) use ($envs) {
                    if (empty($value)) {
                        throw new \Exception('env name cannot be empty');
                    }

                    if (isset($envs[$value])) {
                        throw new \Exception(sprintf('env %s already exists', $value));
                    }

                    return $value;
                }),
                alreadyDeliveredLabel: $this->getRequiredValue('"Already delivered" label name'),
                toDeliverLabel: $this->getRequiredValue('"To deliver" label name'),
            );

            $envs[$newEnv->getName()] = $newEnv;

            $this->io->success(sprintf('Successfully added new env %s', $newEnv->getName()));
        } while (Interactive::YES === $this->io->choice('Add another env ?', [Interactive::YES, Interactive::NO], Interactive::YES));

        return array_values($envs);
    }
}