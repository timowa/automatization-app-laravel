<?php

namespace App\Services;

class ChangelogService
{
    public const SESSION_SEEN_VERSION_KEY = 'product_version_seen';

    public function current(): string
    {
        return (string) config('changelog.current', '1.0');
    }

    public function currentLabel(): string
    {
        return 'v'.$this->current();
    }

    /**
     * Показать бейдж New, если пользователь ещё не видел текущую версию в этой сессии.
     * После первого показа версия помечается просмотренной — на следующих запросах бейджа нет.
     */
    public function consumeNewBadge(): bool
    {
        $current = $this->current();
        $seen = session(self::SESSION_SEEN_VERSION_KEY);

        $showNew = $seen !== $current;

        if ($showNew) {
            session([self::SESSION_SEEN_VERSION_KEY => $current]);
        }

        return $showNew;
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
