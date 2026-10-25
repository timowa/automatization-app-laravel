<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vk;

use App\Services\Vk\VkApiService;
use PHPUnit\Framework\TestCase;
use VK\Exceptions\VKClientException;

class DirectMessageBatchTest extends TestCase
{
    public function test_maps_execute_errors_onto_failed_messages_only(): void
    {
        $results = VkApiService::mapDirectMessageResults([
            ['user_id' => 11],
            ['user_id' => 12],
            ['user_id' => 13],
        ], [
            'response' => [
                ['user_id' => 11, 'response' => 501],
                ['user_id' => 12, 'response' => false],
                ['user_id' => 13, 'response' => false],
            ],
            'execute_errors' => [
                ['error_code' => 901, 'error_msg' => 'privacy'],
                ['error_code' => 15, 'error_msg' => 'Permission to perform this action is denied'],
            ],
        ]);

        $this->assertSame(501, $results[0]['message_id']);
        $this->assertNull($results[0]['error']);
        $this->assertSame(901, $results[1]['error_code']);
        $this->assertSame('privacy', $results[1]['error']);
        $this->assertSame(15, $results[2]['error_code']);
        $this->assertSame(0, $results[2]['message_id']);
    }

    public function test_maps_raw_execute_response_list(): void
    {
        $results = VkApiService::mapDirectMessageResults([
            ['user_id' => 11],
            ['user_id' => 12],
        ], [
            'response' => [501, false],
            'execute_errors' => [
                ['error_code' => 901, 'error_msg' => 'privacy'],
            ],
        ]);

        $this->assertSame(501, $results[0]['message_id']);
        $this->assertNull($results[0]['error']);
        $this->assertSame(0, $results[1]['message_id']);
        $this->assertSame(901, $results[1]['error_code']);
    }

    public function test_execute_code_has_no_non_boolean_conditions(): void
    {
        $history = VkApiService::outgoingHistoryExecuteCode([10, 20]);
        $send = VkApiService::directMessagesExecuteCode([
            ['user_id' => 10, 'message' => 'Привет', 'random_id' => 5],
            ['user_id' => 11, 'message' => 'Ещё', 'random_id' => 6, 'attachment' => 'photo1_2'],
        ]);

        $this->assertSame(
            'return [API.messages.getHistory({"user_id":10,"count":20}),API.messages.getHistory({"user_id":20,"count":20})];',
            $history,
        );
        $this->assertStringNotContainsString('if (', $history);
        $this->assertStringNotContainsString('if (', $send);
        $this->assertStringContainsString('"attachment":"photo1_2"', $send);
        $this->assertStringNotContainsString('attachment', explode(',', $send)[0]);
    }

    public function test_outgoing_history_keeps_only_users_with_an_outgoing_message(): void
    {
        $ids = VkApiService::userIdsWithOutgoingMessages([10, 20, 30], [
            ['items' => [['out' => 0], ['out' => 1]]],
            false,
            ['items' => [['out' => 0]]],
        ]);

        $this->assertSame([10], $ids);
    }

    public function test_rejects_execute_body_without_a_list(): void
    {
        $this->expectException(VKClientException::class);

        VkApiService::mapDirectMessageResults([
            ['user_id' => 11],
        ], [
            'response' => ['unexpected' => true],
        ]);
    }
}
