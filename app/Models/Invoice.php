<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invoice extends Model
{
    protected $fillable = [
        'id', 'invoice_number', 'enrollment_id', 'provider_id', 'student_id', 
        'amount', 'currency', 'status', 'expires_at', 'payment_reference'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
            $model->invoice_number = 'THZ-INV-'.date('Y').'-'.str_pad(rand(1,999999),6,'0',STR_PAD_LEFT);
            $model->payment_reference = Str::uuid();
        });
    }

    public function enrollment() { return $this->belongsTo(Enrollment::class); }
    public function payments() { return $this->hasMany(PaymentTransaction::class); }
}
