<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = ['payment_id', 'receipt_number', 'amount', 'transaction_reference'];
}
