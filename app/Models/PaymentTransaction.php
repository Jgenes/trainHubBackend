<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';

    protected $fillable = [
        'invoice_id', 'provider_id', 'method_type', 'method_name',
        'payer_msisdn', 'gateway_reference', 'status',
        'raw_init_payload', 'raw_callback_payload',
    ];

    protected function casts(): array
    {
        return [
            'raw_init_payload'     => 'array',
            'raw_callback_payload' => 'array',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
