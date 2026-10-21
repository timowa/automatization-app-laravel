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

    protected function prepareForValidation(): void
    {
        if (! $this->has('friend_request_welcome_enabled')) {
            $this->merge(['friend_request_welcome_enabled' => false]);
        }

        if (! $this->has('remove_welcome_card')) {
            $this->merge(['remove_welcome_card' => false]);
        }
    }

    public function rules(): array
    {
        return [
            'wish_happy_birthday_male' => ['required', 'boolean'],
            'wish_happy_birthday_female' => ['required', 'boolean'],
            'birthday_wish_male_text' => ['nullable', 'string', 'max:4096'],
            'birthday_wish_female_text' => ['nullable', 'string', 'max:4096'],
            'friend_request_welcome_enabled' => ['required', 'boolean'],
            'friend_request_welcome_text' => ['nullable', 'string', 'max:4096'],
            'remove_welcome_card' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'wish_happy_birthday_male.required' => 'Укажите настройку поздравлений для мужчин',
            'wish_happy_birthday_male.boolean' => 'Некорректное значение настройки поздравлений для мужчин',
            'wish_happy_birthday_female.required' => 'Укажите настройку поздравлений для женщин',
            'wish_happy_birthday_female.boolean' => 'Некорректное значение настройки поздравлений для женщин',
            'birthday_wish_male_text.string' => 'Текст поздравления для мужчин должен быть строкой',
            'birthday_wish_male_text.max' => 'Текст поздравления для мужчин слишком длинный',
            'birthday_wish_female_text.string' => 'Текст поздравления для женщин должен быть строкой',
            'birthday_wish_female_text.max' => 'Текст поздравления для женщин слишком длинный',
            'friend_request_welcome_enabled.required' => 'Укажите настройку автоприветствия на заявку',
            'friend_request_welcome_enabled.boolean' => 'Некорректное значение настройки автоприветствия на заявку',
            'friend_request_welcome_text.string' => 'Текст приветственного письма должен быть строкой',
            'friend_request_welcome_text.max' => 'Текст приветственного письма слишком длинный',
        ];
    }
}
