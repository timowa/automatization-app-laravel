<?php

declare(strict_types=1);

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LlmClient
{
    /**
     * @throws RuntimeException при ошибке API, таймауте, пустом ответе
     */
    public function chat(string $model, string $systemPrompt, string $userContent, int $timeout = 60): string
    {
        $baseUrl = config('services.ollama.base_url');
        $apiKey = config('services.ollama.api_key');

        if (empty($baseUrl)) {
            throw new RuntimeException('Ollama base URL не настроен');
        }

        $url = rtrim($baseUrl, '/').'/chat';

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'think' => 'medium',
                'options' => [
                    'temperature' => 0.7
                ],
                'stream' => false
            ]);

        if (! $response->ok()) {
            throw new RuntimeException(
                sprintf('Ошибка Ollama API: HTTP %d', $response->status())
            );
        }

        $data = $response->json();
        $content = $data['message']['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Ollama API вернул пустой контент');
        }

        return $content;
    }
}
