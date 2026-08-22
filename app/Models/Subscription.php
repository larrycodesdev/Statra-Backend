<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'paystack_subscription_code',
        'paystack_customer_code',
        'paystack_email_token',
        'plan_code',
        'status',
        'amount',
        'currency',
        'starts_at',
        'expires_at',
        'next_billing_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'      => 'datetime',
            'expires_at'     => 'datetime',
            'next_billing_at' => 'datetime',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'user_id', 'user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at?->isFuture();
    }
}
