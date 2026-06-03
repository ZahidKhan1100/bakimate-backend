<?php

namespace App\Http\Requests;

use App\Rules\UniqueCustomerPhoneInShop;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
        $customerId = (int) $this->route('customerId');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', new UniqueCustomerPhoneInShop($shopId, $customerId)],
            'goal_amount_sen' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'goal_target_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
