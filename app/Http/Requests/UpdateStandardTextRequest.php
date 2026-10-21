<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStandardTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.string' => 'Текст должен быть строкой',
            'body.max' => 'Текст слишком длинный',
        ];
    }
}
