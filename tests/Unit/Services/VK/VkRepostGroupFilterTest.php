<?php

declare(strict_types=1);

namespace Tests\Unit\Services\VK;

use App\Services\Vk\VkApiService;
use PHPUnit\Framework\TestCase;

class VkRepostGroupFilterTest extends TestCase
{
    public function test_keeps_only_numeric_ids_the_user_can_post_to(): void
    {
        $matched = VkApiService::groupIdsUserCanPost(
            [239430475, '100', ['id' => 200], ['name' => 'без id']],
            ['239430475', 200, 300],
        );

        $this->assertSame([239430475, 200], $matched);
    }

    public function test_returns_empty_list_when_user_has_no_matching_groups(): void
    {
        $this->assertSame([], VkApiService::groupIdsUserCanPost([1, 2], [239430475]));
    }
}
