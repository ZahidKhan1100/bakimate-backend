<?php

namespace App\Http\Requests;

use App\Rules\UniqueCustomerPhoneInShop;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
        $shopId = (int) ($this->user()?->shops()->value('id') ?? 0);

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', new UniqueCustomerPhoneInShop($shopId)],
        ];
    }
}
