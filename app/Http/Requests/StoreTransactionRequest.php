<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
        $shopId = $this->user()?->shops()->value('id');

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('shop_id', $shopId)),
            ],
            'amount_sen' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', Rule::in([Transaction::TYPE_CREDIT, Transaction::TYPE_PAYMENT])],
            'note' => ['nullable', 'string', 'max:2000'],
            /** Quick item label for credit analytics (e.g. Phone, Fridge). */
            'item_key' => ['nullable', 'string', 'max:80'],
            /** Qist goal: total contract size in sen — updated on this credit if provided. */
            'goal_amount_sen' => ['nullable', 'integer', 'min:1'],
            'goal_target_date' => ['nullable', 'date'],
            /** Optional; meaningful for credit (“Gave”). Stored on the customer row. Date only (YYYY-MM-DD). */
            'next_due_at' => ['nullable', 'date'],
            /** When the credit/payment happened (date only). Defaults to now if omitted. */
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
