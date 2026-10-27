<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'reference', 'user_id', 'course_id', 'cohort_id',
        'first_name', 'last_name', 'email', 'phone_number',
        'amount', 'currency', 'description', 'status', 'tracking_id',
        'organization', 'position', 'street', 'region', 'city', 'postal',
        'user_name', 'expires_at', 'payment_method',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class, 'cohort_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'payment_id');
    }

    public function receipt()
    {
        return $this->hasOne(Receipt::class, 'payment_id');
    }

    public function disputes()
    {
        return $this->hasMany(Dispute::class, 'payment_id');
    }

    public function isPaid(): bool
    {
        return in_array(strtoupper((string) $this->status), ['COMPLETED', 'PAID', 'SUCCESS'], true);
    }
}
