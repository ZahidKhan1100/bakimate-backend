<?php

namespace App\Rules;

use App\Models\Customer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueCustomerPhoneInShop implements ValidationRule
{
    private const MIN_DIGITS = 8;

    public function __construct(
        private readonly int $shopId,
        private readonly ?int $exceptCustomerId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', $value);
        if ($digits === '' || strlen($digits) < self::MIN_DIGITS) {
            return;
        }

        $query = Customer::query()
            ->where('shop_id', $this->shopId)
            ->whereNotNull('phone');

        if ($this->exceptCustomerId !== null) {
            $query->whereKeyNot($this->exceptCustomerId);
        }

        foreach ($query->get(['id', 'name', 'phone']) as $customer) {
            $existing = preg_replace('/\D/', '', (string) $customer->phone);
            if ($existing !== '' && strlen($existing) >= self::MIN_DIGITS && $existing === $digits) {
                $fail(__('This phone number is already used for :name.', ['name' => $customer->name]));

                return;
            }
        }
    }
}
