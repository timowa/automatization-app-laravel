<?php

declare(strict_types=1);

namespace App\Services\Vk\Comment;

use App\Services\StandardCommentService;

final class CommentTextProvider
{
    public function __construct(
        private readonly StandardCommentService $comments = new StandardCommentService,
    ) {}

    public function getRandomText(): ?string
    {
        return $this->comments->randomBody();
    }
}
