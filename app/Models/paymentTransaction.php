<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'id', 'invoice_id', 'provider_id', 'method_type', 'method_name', 
        'payer_msisdn', 'gateway_reference', 'status', 'raw_init_payload', 'raw_callback_payload'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) { $model->id = (string) Str::uuid(); });
    }

    public function invoice() { return $this->belongsTo(Invoice::class); }
}
