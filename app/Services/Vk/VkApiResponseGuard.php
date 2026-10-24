<?php

declare(strict_types=1);

namespace App\Services\Vk;

use App\Logging\SensitiveDataRedactor;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

/**
 * Приводит ответ ВКонтакте к виду, который vk-php-sdk может разобрать,
 * и пишет код ошибки до того, как SDK потеряет его на пустом error_msg.
 */
final class VkApiResponseGuard
{
    public function __construct(
        private readonly SensitiveDataRedactor $redactor,
    ) {}

    public function sanitize(ResponseInterface $response): ResponseInterface
    {
        $body = (string) $response->getBody();
        $normalized = $this->normalizeBody($body, $response->getStatusCode());

        return $response->withBody(Utils::streamFor($normalized));
    }

    public function normalizeBody(string $body, int $status): string
    {
        if ($status !== 200) {
            $this->log('ВКонтакте вернул ответ не 200', [
                'http_status' => $status,
                'response' => $this->redactBody($body),
            ]);
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded) || ! isset($decoded['error']) || ! is_array($decoded['error'])) {
            return $body;
        }

        $message = $decoded['error']['error_msg'] ?? null;
        if (is_string($message)) {
            return $body;
        }

        $this->log('ВКонтакте вернул ошибку без текста', [
            'error_code' => $decoded['error']['error_code'] ?? null,
            'http_status' => $status,
            'response' => $this->redactPayload($decoded),
        ]);

        $decoded['error']['error_msg'] = '';
        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : $body;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $message, array $context): void
    {
        Log::channel('vk')->error($message, $context);
    }

    private function redactBody(string $body): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $encoded = json_encode($this->redactPayload($decoded), JSON_UNESCAPED_UNICODE);

            return is_string($encoded) ? $encoded : $this->redactor->redactString($body);
        }

        return $this->redactor->redactString($body);
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    private function redactPayload(array $payload): array
    {
        $this->blankRequestTokens($payload);

        $redacted = $this->redactor->redactValue($payload);

        return is_array($redacted) ? $redacted : [];
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function blankRequestTokens(array &$payload): void
    {
        $params = $payload['error']['request_params'] ?? null;
        if (! is_array($params)) {
            return;
        }

        foreach ($params as $index => $param) {
            if (! is_array($param)) {
                continue;
            }

            $key = $param['key'] ?? null;
            if (is_string($key) && preg_match('/token|secret/i', $key) === 1) {
                $payload['error']['request_params'][$index]['value'] = '***';
            }
        }
    }
}
