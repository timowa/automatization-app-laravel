<?php

namespace App\Enums;

enum PublicationTaskStatus: string
{
    case WAITING = 'waiting';
    case PENDING = 'pending';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::WAITING => 'Ожидает', self::PENDING, self::QUEUED => 'В очереди',
            self::PROCESSING => 'Выполняется', self::SUCCESS => 'Успешно', self::FAILED => 'Ошибка',
        };
    }
}
