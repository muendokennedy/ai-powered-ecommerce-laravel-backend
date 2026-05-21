<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDetail extends Model
{
    protected $table = 'payment_details';

    protected $fillable = [
        'order_id',
        'payment_method',
        'payment_status',
        'payment_details',
    ];

    protected $casts = [
        'payment_details' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
