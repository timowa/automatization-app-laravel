<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Models\Agent;
use Carbon\Carbon;
use RuntimeException;

class WeeklySummaryLlmGenerator
{
    public function __construct(
        private readonly LlmClient $llmClient
    ) {}

    /**
     * @throws RuntimeException при ошибке LLM
     */
    public function generate(
        Agent $agent,
        Carbon $from,
        Carbon $to,
        int $activeSale,
        int $activeRent,
        int $completedSold,
        int $completedRented
    ): string {
        $payload = [
            'agent' => [
                'name' => $agent->name,
            ],
            'period' => [
                'from' => $from->format('d.m.Y'),
                'to' => $to->format('d.m.Y'),
            ],
            'active' => [
                'sale' => $activeSale,
                'rent' => $activeRent,
            ],
            'completed' => [
                'sold' => $completedSold,
                'rented' => $completedRented,
            ],
        ];

        $systemPrompt = <<<'PROMPT'
Ты помогаешь агенту по недвижимости писать еженедельные
посты для его страницы VK.

Пиши от первого лица агента.

Используй ТОЛЬКО факты, переданные в JSON.
Не придумывай адреса, цены, клиентов, причины сделок
и другие сведения.

Пост должен:
- быть живым и естественным;
- выглядеть как личный пост агента;
- содержать итоги недели;
- упоминать количество активных объектов на продажу;
- упоминать количество активных объектов в аренду;
- упоминать количество проданных объектов;
- упоминать количество сданных объектов;
- не выглядеть как официальный отчёт;
- не быть чрезмерно рекламным.

Объём: 500–800 символов.

Не добавляй заголовки вроде "Еженедельный отчёт агентства".
PROMPT;

        $model = config('services.ollama.model', 'deepseek-v4-flash');
        $userContent = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $this->llmClient->chat($model, $systemPrompt, $userContent);
    }
}
