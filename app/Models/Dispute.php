<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    protected $fillable = ['payment_id', 'reason', 'status'];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
