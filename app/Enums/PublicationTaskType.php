<?php

namespace App\Enums;

enum PublicationTaskType: string
{
    case VK_POST = 'vk_post';
    case VK_COMMENT = 'vk_comment';
    case VK_STORY = 'vk_story';
    case VK_LOOP_STORY = 'vk_loop_story';
    case VK_END_LOOP_STORY = 'vk_end_loop_story';
    case VK_REPOST = 'vk_repost';
    case VK_CREATE_PRODUCT = 'vk_create_product';
    case VK_EDIT_PRODUCT = 'vk_edit_product';
    case VK_ARCHIVE_PRODUCT = 'vk_archive_product';
}
