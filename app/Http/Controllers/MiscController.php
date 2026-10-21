<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StandardTextKey;
use App\Http\Requests\StoreStandardCommentRequest;
use App\Http\Requests\UpdateStandardCommentRequest;
use App\Http\Requests\UpdateStandardTextRequest;
use App\Models\StandardComment;
use App\Services\StandardCommentService;
use App\Services\StandardTextRenderer;
use App\Services\StandardTextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MiscController extends Controller
{
    public function index(
        StandardTextService $texts,
        StandardCommentService $comments,
    ): View {
        return view('misc', [
            'texts' => $texts->allBodiesByKey(),
            'comments' => $comments->allOrdered(),
            'placeholders' => StandardTextRenderer::placeholders(),
            'keys' => [
                'birthday_male' => StandardTextKey::BirthdayWishMale->value,
                'birthday_female' => StandardTextKey::BirthdayWishFemale->value,
                'birthday_discount' => StandardTextKey::BirthdayDiscount->value,
                'welcome' => StandardTextKey::FriendRequestWelcome->value,
            ],
        ]);
    }

    public function updateText(
        UpdateStandardTextRequest $request,
        string $key,
        StandardTextService $texts,
    ): JsonResponse {
        $enum = StandardTextKey::tryFrom($key);
        if ($enum === null) {
            return response()->json([
                'success' => false,
                'messages' => ['Неизвестный тип текста'],
            ], 422);
        }

        $body = (string) ($request->input('body') ?? '');
        $saved = $texts->upsert($enum, $body);

        Log::channel('job')->info('Стандартный текст обновлён', [
            'key' => $enum->value,
        ]);

        return response()->json([
            'success' => true,
            'messages' => ['Сохранено'],
            'key' => $saved->key->value,
            'body' => (string) ($saved->body ?? ''),
        ]);
    }

    public function storeComment(
        StoreStandardCommentRequest $request,
        StandardCommentService $comments,
    ): JsonResponse {
        $body = trim((string) $request->input('body'));
        $comment = $comments->create($body);

        Log::channel('job')->info('Стандартный комментарий создан', [
            'comment_id' => $comment->id,
        ]);

        return response()->json([
            'success' => true,
            'messages' => ['Комментарий добавлен'],
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
            ],
        ]);
    }

    public function updateComment(
        UpdateStandardCommentRequest $request,
        int $id,
        StandardCommentService $comments,
    ): JsonResponse {
        $comment = StandardComment::query()->findOrFail($id);
        $body = trim((string) $request->input('body'));
        $updated = $comments->update($comment, $body);

        Log::channel('job')->info('Стандартный комментарий обновлён', [
            'comment_id' => $updated->id,
        ]);

        return response()->json([
            'success' => true,
            'messages' => ['Комментарий сохранён'],
            'comment' => [
                'id' => $updated->id,
                'body' => $updated->body,
            ],
        ]);
    }

    public function destroyComment(
        int $id,
        StandardCommentService $comments,
    ): JsonResponse {
        $comment = StandardComment::query()->findOrFail($id);
        $comments->delete($comment);

        Log::channel('job')->info('Стандартный комментарий удалён', [
            'comment_id' => $id,
        ]);

        return response()->json([
            'success' => true,
            'messages' => ['Комментарий удалён'],
            'id' => $id,
        ]);
    }
}
