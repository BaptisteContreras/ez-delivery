<?php

namespace Ezdeliver\Token;

use Symfony\Component\Filesystem\Filesystem;

final class TokenVault
{
    public function __construct(
        private readonly Filesystem $fs,
        private readonly string $vaultFilePath,
    ) {
    }

    public function get(string $ref): string
    {
        $tokens = $this->readAll();

        if (!isset($tokens[$ref])) {
            throw new TokenNotFoundException($ref);
        }

        return $tokens[$ref];
    }

    public function has(string $ref): bool
    {
        return isset($this->readAll()[$ref]);
    }

    public function set(string $ref, string $token): void
    {
        $tokens = $this->readAll();
        $tokens[$ref] = $token;
        $this->writeAll($tokens);
    }

    public function findRefByValue(string $token): ?string
    {
        foreach ($this->readAll() as $ref => $value) {
            if ($value === $token) {
                return $ref;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function listRefs(): array
    {
        return array_keys($this->readAll());
    }

    /**
     * @return array<string, string>
     */
    private function readAll(): array
    {
        if (!$this->fs->exists($this->vaultFilePath)) {
            return [];
        }

        $content = file_get_contents($this->vaultFilePath);
        if ($content === false) {
            return [];
        }

        return json_decode($content, true) ?? [];
    }

    /**
     * @param array<string, string> $tokens
     */
    private function writeAll(array $tokens): void
    {
        $json = json_encode($tokens, JSON_PRETTY_PRINT);
        if ($json === false) {
            $json = '{}';
        }
        $this->fs->dumpFile($this->vaultFilePath, $json);
    }
}
