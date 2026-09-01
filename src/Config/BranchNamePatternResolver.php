<?php

namespace Ezdeliver\Config;

use Ezdeliver\Config\Model\ProjectEnvConfig;

final class BranchNamePatternResolver
{
    private const array SUPPORTED_VARIABLES = ['env', 'date', 'date_time', 'date_full'];
    private const array DYNAMIC_VARIABLES = ['date', 'date_time', 'date_full'];

    public function resolveDefaultBranchName(ProjectEnvConfig $env, \DateTimeImmutable $now): string
    {
        return $this->resolve($env->getBranchNamePattern(), $env->getName(), $now);
    }

    public function resolve(string $pattern, string $envName, \DateTimeImmutable $now): string
    {
        return strtr($pattern, [
            '%env%' => $envName,
            '%date%' => $now->format('Y-m-d'),
            '%date_time%' => $now->format('Y-m-d_H-i'),
            '%date_full%' => $now->format('Y-m-d_H-i-s'),
        ]);
    }

    /**
     * True when the pattern contains none of the date-based variables, meaning it
     * resolves to the same branch name on every call (a prerequisite for the
     * delete-and-recreate release-branch workflow).
     */
    public function isStatic(string $pattern): bool
    {
        preg_match_all('/%([a-zA-Z_]+)%/', $pattern, $matches);

        return [] === array_intersect($matches[1], self::DYNAMIC_VARIABLES);
    }

    /**
     * @throws \Exception when the pattern references an unsupported variable
     */
    public function validate(string $pattern): void
    {
        preg_match_all('/%([a-zA-Z_]+)%/', $pattern, $matches);

        $unknownVariables = array_unique(array_diff($matches[1], self::SUPPORTED_VARIABLES));

        if (!empty($unknownVariables)) {
            throw new \Exception(sprintf(
                'Unknown branch name pattern variable(s): %s. Supported variables: %s',
                implode(', ', array_map(fn (string $variable) => sprintf('%%%s%%', $variable), $unknownVariables)),
                implode(', ', array_map(fn (string $variable) => sprintf('%%%s%%', $variable), self::SUPPORTED_VARIABLES))
            ));
        }
    }
}
