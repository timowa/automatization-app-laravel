<?php

declare(strict_types=1);

namespace App\Enums;

enum PublicationTaskType: string
{
    case VK_UPLOAD_IMAGES = 'vk_upload_images';
    case VK_POST = 'vk_post';
    case VK_LIKE = 'vk_like';
    case VK_COMMENT = 'vk_comment';
    case VK_STORY = 'vk_story';
    case VK_LOOP_STORY = 'vk_loop_story';
    case VK_END_LOOP_STORY = 'vk_end_loop_story';
    case VK_REPOST = 'vk_repost';
    case VK_CREATE_PRODUCT = 'vk_create_product';
    case VK_EDIT_PRODUCT = 'vk_edit_product';
    case VK_ARCHIVE_PRODUCT = 'vk_archive_product';

    public function label(): string
    {
        return match ($this) {
            self::VK_UPLOAD_IMAGES => 'Загрузка фото',
            self::VK_POST => 'Публикация поста',
            self::VK_LIKE => 'Лайк поста',
            self::VK_COMMENT => 'Комментарий',
            self::VK_STORY => 'Публикация истории',
            self::VK_LOOP_STORY => 'Циклическая история',
            self::VK_END_LOOP_STORY => 'Остановка циклической истории',
            self::VK_REPOST => 'Репост',
            self::VK_CREATE_PRODUCT => 'Создание товара',
            self::VK_EDIT_PRODUCT => 'Редактирование товара',
            self::VK_ARCHIVE_PRODUCT => 'Архивация товара',
        };
    }
}
