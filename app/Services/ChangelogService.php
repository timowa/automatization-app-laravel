<?php

namespace App\Services;

class ChangelogService
{
    public function current(): string
    {
        return (string) config('changelog.current', '1.0');
    }

    public function currentLabel(): string
    {
        return 'v'.$this->current();
    }

    /**
     * @return list<array{version: string, date: string, changes: list<string>}>
     */
    public function all(): array
    {
        $releases = config('changelog.releases', []);

        if (! is_array($releases)) {
            return [];
        }

        $normalized = [];

        foreach ($releases as $release) {
            if (! is_array($release) || ! isset($release['version'])) {
                continue;
            }

            $changes = $release['changes'] ?? [];
            if (! is_array($changes)) {
                $changes = [];
            }

            $normalized[] = [
                'version' => (string) $release['version'],
                'date' => (string) ($release['date'] ?? ''),
                'changes' => array_values(array_map('strval', $changes)),
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{version: string, date: string, changes: list<string>}>
     */
    public function latest(int $limit = 4): array
    {
        return array_slice($this->all(), 0, max(0, $limit));
    }
}
