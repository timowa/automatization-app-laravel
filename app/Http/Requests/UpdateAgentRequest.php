<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $agentId = $this->route('id');

        return [
            'name' => ['required', 'string', 'min:5'],
            'phone' => [
                'required',
                'string',
                'regex:/^7\d{10}$/',
                Rule::unique('agents', 'phone')->ignore($agentId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите ФИО',
            'name.min' => 'ФИО должно содержать не менее 5 символов',
            'phone.required' => 'Укажите номер телефона',
            'phone.regex' => 'Номер телефона должен соответствовать формату РФ (+7 XXX XXX-XX-XX)',
            'phone.unique' => 'Агент с таким номером телефона уже существует',
        ];
    }
}
