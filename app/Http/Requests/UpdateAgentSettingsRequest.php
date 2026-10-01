<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wish_happy_birthday' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'wish_happy_birthday.required' => 'Укажите настройку поздравлений',
            'wish_happy_birthday.boolean' => 'Некорректное значение настройки поздравлений',
        ];
    }
}
