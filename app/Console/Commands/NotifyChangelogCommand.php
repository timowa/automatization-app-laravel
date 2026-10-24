<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ChangelogService;
use App\Services\Vk\VkApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotifyChangelogCommand extends Command
{
    protected $signature = 'vk:notify-changelog';

    protected $description = 'Отправка уведомления о последней версии из changelog, если она ещё не отправлялась';

    public function handle(VkApiService $vkApi, ChangelogService $changelog): int
    {
        $releases = $changelog->latest(1);

        if ($releases === []) {
            $this->warn('В changelog нет релизов');

            return self::SUCCESS;
        }

        $version = $releases[0]['version'];

        if ($this->lastNotifiedVersion() === $version) {
            Log::channel('job')->info('Уведомление о версии '.$version.' не отправлено: версия не изменилась');
            $this->info('Версия не изменилась, уведомление не отправлено');

            return self::SUCCESS;
        }

        $text = $this->buildMessage($releases[0]);

        try {
            $vkApi->sendTechMessage($text);
        } catch (\Throwable $e) {
            Log::channel('vk')->error($e->getMessage());
            Log::channel('job')->warning('Ошибка отправки уведомления о версии');
            $this->error('Не удалось отправить уведомление о версии');

            return self::SUCCESS;
        }

        $this->rememberNotifiedVersion($version);
        Log::channel('job')->info('Уведомление о версии '.$version.' отправлено');
        $this->info('Уведомление о версии отправлено');

        return self::SUCCESS;
    }

    /**
     * @param  array{version: string, date: string, changes: list<string>}  $release
     */
    private function buildMessage(array $release): string
    {
        $header = 'Обновление v'.$release['version'];

        if ($release['date'] !== '') {
            $header .= ' ('.$release['date'].')';
        }

        $lines = [$header, ''];

        foreach ($release['changes'] as $change) {
            $lines[] = '• '.$change;
        }

        return implode("\n", $lines);
    }

    private function lastNotifiedVersion(): ?string
    {
        $version = DB::table('changelog_notifications')
            ->orderByDesc('id')
            ->value('version');

        if ($version === null) {
            return null;
        }

        return (string) $version;
    }

    private function rememberNotifiedVersion(string $version): void
    {
        DB::table('changelog_notifications')->insert([
            'version' => $version,
            'notified_at' => now(),
        ]);
    }
}
