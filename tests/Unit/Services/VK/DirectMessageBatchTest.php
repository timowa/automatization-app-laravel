<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vk;

use App\Services\Vk\VkApiService;
use PHPUnit\Framework\TestCase;
use VK\Exceptions\VKClientException;

class DirectMessageBatchTest extends TestCase
{
    public function test_maps_three_welcome_report_kinds(): void
    {
        $results = VkApiService::mapWelcomeReports([
            ['user_id' => 10, 'message' => 'a', 'random_id' => 1, 'attachment' => ''],
            ['user_id' => 11, 'message' => 'b', 'random_id' => 2, 'attachment' => ''],
            ['user_id' => 12, 'message' => 'c', 'random_id' => 3, 'attachment' => ''],
        ], [
            ['user_id' => 10, 'message_id' => null, 'error' => VkApiService::ALREADY_WROTE_ERROR],
            ['user_id' => 11, 'message_id' => 501, 'error' => null],
            ['user_id' => 12, 'message_id' => null, 'error' => false],
        ]);

        $this->assertSame(VkApiService::ALREADY_WROTE_ERROR, $results[0]['error']);
        $this->assertNull($results[0]['message_id']);
        $this->assertSame(501, $results[1]['message_id']);
        $this->assertNull($results[1]['error']);
        $this->assertNull($results[2]['message_id']);
        $this->assertNull($results[2]['error']);
    }

    public function test_rejects_execute_body_without_a_report_per_request(): void
    {
        $this->expectException(VKClientException::class);

        VkApiService::mapWelcomeReports([
            ['user_id' => 11, 'message' => 'a', 'random_id' => 1, 'attachment' => ''],
            ['user_id' => 12, 'message' => 'b', 'random_id' => 2, 'attachment' => ''],
        ], [
            ['user_id' => 11, 'message_id' => 501, 'error' => null],
        ]);
    }

    public function test_execute_code_branches_only_on_booleans(): void
    {
        $code = VkApiService::welcomeExecuteCode([
            ['user_id' => 10, 'message' => "Привет\n\"друг\"", 'random_id' => 5, 'attachment' => ''],
            ['user_id' => 11, 'message' => 'Ещё', 'random_id' => 6, 'attachment' => 'photo1_2'],
        ]);

        $this->assertStringContainsString('var res = [];', $code);
        $this->assertStringContainsString('var hasHistory = history.count > 0;', $code);
        $this->assertStringContainsString('if (hasHistory)', $code);
        $this->assertStringContainsString('var hasAttachment = item.attachment.length > 0;', $code);
        $this->assertStringContainsString('var sentId = parseInt("" + sent);', $code);
        $this->assertStringContainsString('var sentOk = sentId > 0;', $code);
        $this->assertStringContainsString(VkApiService::ALREADY_WROTE_ERROR, $code);
        $this->assertStringContainsString('photo1_2', $code);
        $this->assertStringNotContainsString('if (history)', $code);
        $this->assertStringNotContainsString('if (item.attachment)', $code);
        $this->assertStringNotContainsString('sent > 0', $code);
        $this->assertSame(1, substr_count($code, '"attachment":item.attachment'));
        $this->assertStringContainsString('return res;', $code);
    }

    public function test_execute_code_goes_in_post_body_not_the_uri(): void
    {
        $code = 'return [API.messages.send({"message":"длинное приветствие"})];';
        $request = VkApiService::executeRequest($code, 'vk1.secret-token', '5.199', 'ru');

        $this->assertSame('https://api.vk.com/method/execute', $request['uri']);
        $this->assertStringNotContainsString('code=', $request['uri']);
        $this->assertStringNotContainsString('vk1.secret-token', $request['uri']);
        $this->assertSame(
            ['code', 'access_token', 'v', 'lang'],
            array_column($request['multipart'], 'name'),
        );
        $this->assertSame($code, $request['multipart'][0]['contents']);
        $this->assertSame('vk1.secret-token', $request['multipart'][1]['contents']);
    }
}
