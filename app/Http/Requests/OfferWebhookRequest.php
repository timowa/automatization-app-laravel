<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfferWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'offers' => ['required', 'array'],
            'offers.*.id' => ['required', 'numeric'],
            'offers.*.code' => ['required', 'string'],
            'offers.*.url' => ['required', 'url'],
            'offers.*.agent.phone' => ['required', 'string'],
            'offers.*.location.city' => ['required', 'string'],
            'offers.*.location.address' => ['required', 'string'],
            'offers.*.deal' => ['required', 'string'],
            'offers.*.category' => ['required', 'string'],
            'offers.*.price' => ['required', 'numeric'],
            'offers.*.status' => ['required', 'string'],
            'offers.*.photos' => ['nullable', 'array'],
            'offers.*.photos.*.url' => ['nullable', 'string'],
            'offers.*.stage' => ['nullable', 'integer', 'between:2,4'],
            'offers.*.rooms' => ['nullable', 'numeric'],
            'offers.*.floor' => ['nullable', 'numeric'],
            'offers.*.floors' => ['nullable', 'numeric'],
            'offers.*.totalArea' => ['nullable', 'numeric', 'decimal:0,2'],
            'offers.*.kitchenArea' => ['nullable', 'numeric', 'decimal:0,2'],
            'offers.*.livingArea' => ['nullable', 'numeric', 'decimal:0,2'],
            'offers.*.ceilingHeight' => ['nullable', 'numeric', 'decimal:0,2'],
            'offers.*.deposit' => ['nullable', 'numeric'],
            'offers.*.commission' => ['nullable', 'numeric'],

        ];
    }
}
