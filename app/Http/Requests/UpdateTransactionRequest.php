<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
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
            'amount_sen' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
            /** Quick-item tag; meaningful for credit rows — ignored server-side for payments. */
            'item_key' => ['nullable', 'string', 'max:80'],
        ];
    }
}
