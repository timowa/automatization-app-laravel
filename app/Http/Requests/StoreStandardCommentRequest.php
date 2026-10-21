<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStandardCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Введите текст комментария',
            'body.string' => 'Текст комментария должен быть строкой',
            'body.max' => 'Текст комментария слишком длинный',
        ];
    }
}
