<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BandOrder extends Model
{
    protected $fillable = [
        'order_number',
        'first_name', 'last_name', 'email', 'phone',
        'street_address', 'city', 'state', 'country',
        'band_size', 'quantity', 'plan',
        'unit_price', 'subtotal', 'discount', 'shipping', 'total',
        'status', 'issue', 'delay_note', 'status_history',
        'payment_status', 'payment_reference', 'korapay_checkout_url',
        'tracking_number', 'courier', 'shipped_at',
        'rating', 'review_text',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'     => 'float',
            'subtotal'       => 'float',
            'discount'       => 'float',
            'shipping'       => 'float',
            'total'          => 'float',
            'shipped_at'     => 'datetime',
            'status_history' => 'array',
            'rating'         => 'integer',
            'owns_band'      => 'boolean',
        ];
    }

    public static function generateOrderNumber(): string
    {
        $year  = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return 'STR-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public function planLabel(): string
    {
        return $this->plan === 'band_care_plan' ? 'Band + Care Plan' : 'Band Only';
    }
}
