<?php

declare(strict_types=1);

namespace App\Services;

final class PublicationTaskErrorPresenter
{
    public function present(?string $error): string
    {
        if ($error === null || trim($error) === '') {
            return 'неизвестная ошибка';
        }

        $normalized = mb_strtolower($error);

        return match (true) {
            str_contains($normalized, 'flood control') => 'flood control',
            str_contains($normalized, 'не найдено групп, в которые пользователь имеет право постить'),
            str_contains($normalized, 'нет групп, в которые пользователь имеет право') => 'у пользователя нет прав',
            str_contains($normalized, 'для агента не задан токен') => 'у агента нет токена ВК',
            str_contains($normalized, 'пост удалён'),
            str_contains($normalized, 'пост удален'),
            str_contains($normalized, 'пост не найден') => 'пост удалён или не найден',
            str_contains($normalized, 'оффер не найден'),
            str_contains($normalized, 'оффер для поста не найден') => 'оффер не найден',
            str_contains($normalized, 'нет стандартных текстов комментариев') => 'нет стандартных текстов комментариев',
            str_contains($normalized, 'captcha') => 'требуется капча',
            default => $this->shorten($error),
        };
    }

    private function shorten(string $error): string
    {
        $error = trim($error);
        if (mb_strlen($error) <= 120) {
            return $error;
        }

        return mb_substr($error, 0, 117).'…';
    }
}
