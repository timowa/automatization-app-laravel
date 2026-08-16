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
}
