<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoiceLedgerParseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transcript' => ['required', 'string', 'min:1', 'max:500'],
            'intent_hint' => ['required', 'string', Rule::in(['credit', 'payment'])],
            'currency_code' => ['required', 'string', 'min:3', 'max:8'],
            'customer_name' => ['required', 'string', 'max:120'],
            'quick_items' => ['sometimes', 'array', 'max:24'],
            'quick_items.*' => ['string', 'max:64'],
            'app_language' => ['sometimes', 'string', 'max:8'],
        ];
    }
}
