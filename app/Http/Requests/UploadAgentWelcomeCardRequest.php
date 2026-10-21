<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\File;

class UploadAgentWelcomeCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'card.required' => 'Выберите файл визитки',
            'card.uploaded' => 'Не удалось загрузить файл. Проверьте размер (до 5 МБ) и формат изображения.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $file = $this->file('card');

        Log::channel('vk-images')->warning('Валидация визитки не пройдена', [
            'agent_id' => $this->route('id'),
            'errors' => $validator->errors()->toArray(),
            'has_file' => $this->hasFile('card'),
            'request_keys' => array_keys($this->all()),
            'files_keys' => array_keys($this->allFiles()),
            'upload_error' => $file?->getError(),
            'client_name' => $file?->getClientOriginalName(),
            'client_mime' => $file?->getClientMimeType(),
            'detected_mime' => $file?->getMimeType(),
            'client_ext' => $file?->getClientOriginalExtension(),
            'size_bytes' => $file?->getSize(),
            'content_type' => $this->header('Content-Type'),
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'messages' => array_values($validator->errors()->all()),
            'errors' => $validator->errors(),
        ], 422));
    }
}
