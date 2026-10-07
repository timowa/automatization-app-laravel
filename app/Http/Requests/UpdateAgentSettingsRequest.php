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
            'birthday_wish_male_text' => ['nullable', 'string', 'max:4096'],
            'birthday_wish_female_text' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'wish_happy_birthday.required' => 'Укажите настройку поздравлений',
            'wish_happy_birthday.boolean' => 'Некорректное значение настройки поздравлений',
            'birthday_wish_male_text.string' => 'Текст поздравления для мужчин должен быть строкой',
            'birthday_wish_male_text.max' => 'Текст поздравления для мужчин слишком длинный',
            'birthday_wish_female_text.string' => 'Текст поздравления для женщин должен быть строкой',
            'birthday_wish_female_text.max' => 'Текст поздравления для женщин слишком длинный',
        ];
    }
}
