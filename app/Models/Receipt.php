<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = [
        'payment_id', 'receipt_number', 'amount', 'transaction_reference',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
