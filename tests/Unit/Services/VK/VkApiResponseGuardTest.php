<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vk;

use App\Logging\SensitiveDataRedactor;
use App\Services\Vk\VkApiResponseGuard;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use VK\Client\VKApiError;
use VK\Exceptions\Api\ExceptionMapper;
use VK\Exceptions\Api\VKApiUnknownException;

class VkApiResponseGuardTest extends TestCase
{
    public function test_missing_error_msg_keeps_vk_error_code_and_hides_token(): void
    {
        $token = 'vk1.supersecretvalue';
        $raw = json_encode([
            'error' => [
                'error_code' => 1,
                'request_params' => [
                    ['key' => 'access_token', 'value' => $token],
                    ['key' => 'method', 'value' => 'stories.save'],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        $this->assertIsString($raw);

        try {
            ExceptionMapper::parse(new VKApiError(['error_code' => 1]));
            $this->fail('SDK должен терять пустой error_msg');
        } catch (\TypeError $e) {
            $this->assertStringContainsString('error_message', $e->getMessage());
        }

        $logged = [];
        Log::listen(function (object $event) use (&$logged): void {
            $logged[] = json_encode([
                'message' => $event->message ?? '',
                'context' => $event->context ?? [],
            ], JSON_UNESCAPED_UNICODE);
        });

        $guard = new VkApiResponseGuard(new SensitiveDataRedactor);
        $response = $guard->sanitize(new Response(200, [], $raw));
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertIsArray($decoded);
        $exception = ExceptionMapper::parse(new VKApiError($decoded['error']));
        $this->assertInstanceOf(VKApiUnknownException::class, $exception);
        $this->assertSame(1, $exception->getErrorCode());

        $encodedLog = implode("\n", $logged);
        $this->assertStringContainsString('"error_code":1', str_replace(' ', '', $encodedLog));
        $this->assertStringNotContainsString($token, $encodedLog);
    }

    public function test_http_405_body_is_logged_without_token(): void
    {
        $token = 'vk1.uploadsecrettoken';
        $logged = [];
        Log::listen(function (object $event) use (&$logged): void {
            $logged[] = json_encode([
                'message' => $event->message ?? '',
                'context' => $event->context ?? [],
            ], JSON_UNESCAPED_UNICODE);
        });

        $guard = new VkApiResponseGuard(new SensitiveDataRedactor);
        $guard->sanitize(new Response(405, [], 'access_token='.$token.' denied'));

        $encodedLog = implode("\n", $logged);
        $this->assertStringContainsString('405', $encodedLog);
        $this->assertStringNotContainsString($token, $encodedLog);
    }
}
