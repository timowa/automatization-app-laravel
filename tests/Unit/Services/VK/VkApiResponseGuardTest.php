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
use VK\Exceptions\Api\VKApiCaptchaException;
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

    public function test_captcha_needed_is_logged_without_session_secret_and_stays_parseable(): void
    {
        $token = 'vk1.captchasecretvalue';
        $session = 'super-session-secret';
        $raw = json_encode([
            'error' => [
                'error_code' => 14,
                'error_msg' => 'Captcha needed',
                'captcha_sid' => '239633676097',
                'captcha_img' => 'https://api.vk.com/captcha.php?sid=239633676097',
                'redirect_uri' => 'https://id.vk.com/not_robot_captcha?session_token='.$session,
                'request_params' => [
                    ['key' => 'access_token', 'value' => $token],
                    ['key' => 'method', 'value' => 'messages.send'],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertIsString($raw);

        $logged = [];
        Log::listen(function (object $event) use (&$logged): void {
            $logged[] = json_encode([
                'message' => $event->message ?? '',
                'context' => $event->context ?? [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        });

        $guard = new VkApiResponseGuard(new SensitiveDataRedactor);
        $body = (string) $guard->sanitize(new Response(200, [], $raw))->getBody();
        $decoded = json_decode($body, true);

        $this->assertIsArray($decoded);
        $exception = ExceptionMapper::parse(new VKApiError($decoded['error']));
        $this->assertInstanceOf(VKApiCaptchaException::class, $exception);
        $this->assertSame(14, $exception->getErrorCode());
        $this->assertSame('239633676097', $exception->getError()->getCaptchaSid());
        $this->assertStringContainsString($session, $body);

        $encodedLog = implode("\n", $logged);
        $this->assertStringContainsString('ВКонтакте запросил капчу', $encodedLog);
        $this->assertStringContainsString('239633676097', $encodedLog);
        $this->assertStringContainsString('messages.send', $encodedLog);
        $this->assertStringContainsString('https://id.vk.com/not_robot_captcha', $encodedLog);
        $this->assertStringNotContainsString($token, $encodedLog);
        $this->assertStringNotContainsString($session, $encodedLog);
        $this->assertStringNotContainsString('без текста', $encodedLog);
    }

    public function test_captcha_without_message_gets_captcha_needed_text(): void
    {
        $raw = json_encode([
            'error' => [
                'error_code' => '14',
                'captcha_sid' => '55',
            ],
        ], JSON_UNESCAPED_UNICODE);
        $this->assertIsString($raw);

        $guard = new VkApiResponseGuard(new SensitiveDataRedactor);
        $decoded = json_decode((string) $guard->sanitize(new Response(200, [], $raw))->getBody(), true);

        $this->assertIsArray($decoded);
        $this->assertSame(14, $decoded['error']['error_code']);
        $this->assertSame('Captcha needed', $decoded['error']['error_msg']);
        $exception = ExceptionMapper::parse(new VKApiError($decoded['error']));
        $this->assertInstanceOf(VKApiCaptchaException::class, $exception);
        $this->assertSame('Captcha needed', $exception->getMessage());
    }
}
