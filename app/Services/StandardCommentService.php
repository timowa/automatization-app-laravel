<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StandardComment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StandardCommentService
{
    public function randomBody(): ?string
    {
        $row = DB::table('standard_comments')
            ->inRandomOrder()
            ->first(['body']);

        if ($row === null || ! is_string($row->body)) {
            return null;
        }

        $body = trim($row->body);

        return $body === '' ? null : $body;
    }

    /**
     * @return Collection<int, StandardComment>
     */
    public function allOrdered(): Collection
    {
        return StandardComment::query()->orderBy('id')->get();
    }

    public function create(string $body): StandardComment
    {
        return StandardComment::query()->create(['body' => $body]);
    }

    public function update(StandardComment $comment, string $body): StandardComment
    {
        $comment->body = $body;
        $comment->save();

        return $comment;
    }

    public function delete(StandardComment $comment): void
    {
        $comment->delete();
    }
}
