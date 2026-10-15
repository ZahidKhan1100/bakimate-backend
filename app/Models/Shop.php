<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    /** @var list<string> */
    public const DEFAULT_CREDIT_QUICK_ITEMS = ['Phone', 'Fridge', 'Grocery', 'Accessory', 'Other'];

    protected $fillable = [
        'user_id',
        'name',
        'primary_currency_code',
        'location',
        'contact',
        'payment_instructions',
        'duitnow_qr_path',
        'credit_quick_items',
        'reference_currency_code',
        'reference_currency_per_myr',
        'subscription_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'subscription_expires_at' => 'datetime',
            'credit_quick_items' => 'array',
            'reference_currency_per_myr' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function subscriptionActive(?\DateTimeInterface $at = null): bool
    {
        if (! config('bakimate.subscriptions_enabled')) {
            return true;
        }

        if ($this->subscription_expires_at === null) {
            return false;
        }

        return $this->subscription_expires_at->isFuture();
    }
}
