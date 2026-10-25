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
        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error']) && $this->isCaptcha($decoded['error'])) {
            return $this->normalizeCaptcha($decoded, $status);
        }

        if ($status !== 200) {
            $this->log('ВКонтакте вернул ответ не 200', [
                'http_status' => $status,
                'response' => $this->redactBody($body),
            ]);
        }

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
     * Капча приходит с текстом, поэтому общий разбор её пропускает.
     * В журнал пишем только номер, картинку и адрес страницы без параметров:
     * в ссылке подтверждения бывает секрет сессии.
     *
     * @param  array<string, mixed>  $decoded
     */
    private function normalizeCaptcha(array $decoded, int $status): string
    {
        $error = $decoded['error'] ?? null;
        if (! is_array($error)) {
            return '{"error":{"error_code":14,"error_msg":"Captcha needed"}}';
        }

        $error['error_code'] = 14;
        $message = $error['error_msg'] ?? null;
        if (! is_string($message) || trim($message) === '') {
            $error['error_msg'] = 'Captcha needed';
        }
        $decoded['error'] = $error;

        $this->log('ВКонтакте запросил капчу', [
            'error_code' => 14,
            'http_status' => $status,
            'method' => $this->requestMethod($error),
            'captcha_sid' => $this->plainString($error['captcha_sid'] ?? null),
            'captcha_img' => $this->redactOptionalString($error['captcha_img'] ?? null),
            'redirect_uri' => $this->redirectLocation($error['redirect_uri'] ?? null),
        ]);

        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{"error":{"error_code":14,"error_msg":"Captcha needed"}}';
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private function isCaptcha(array $error): bool
    {
        $code = $error['error_code'] ?? null;
        if ($code === 14 || $code === '14') {
            return true;
        }

        $message = $error['error_msg'] ?? null;

        return is_string($message) && strcasecmp(trim($message), 'Captcha needed') === 0;
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private function requestMethod(array $error): ?string
    {
        $params = $error['request_params'] ?? null;
        if (! is_array($params)) {
            return null;
        }

        foreach ($params as $param) {
            if (! is_array($param) || ($param['key'] ?? null) !== 'method') {
                continue;
            }

            return $this->plainString($param['value'] ?? null);
        }

        return null;
    }

    private function redirectLocation(mixed $uri): ?string
    {
        if (! is_string($uri) || trim($uri) === '') {
            return null;
        }

        $parts = parse_url($uri);
        if (! is_array($parts)) {
            return null;
        }

        $host = $parts['host'] ?? '';
        if ($host === '') {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $path = $parts['path'] ?? '';

        return $scheme.'://'.$host.$path;
    }

    private function plainString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function redactOptionalString(mixed $value): ?string
    {
        $value = $this->plainString($value);

        return $value === null ? null : $this->redactor->redactString($value);
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
