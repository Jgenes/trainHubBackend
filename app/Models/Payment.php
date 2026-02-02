<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'course_id', 'cohort_id', 'first_name', 
        'email', 'phone_number', 'amount', 'currency', 'status', 
        'organization', 'position', 'street', 'region', 'city', 'postal', 'tracking_id'
    ];
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
    // Hii inaiambia Laravel kuwa payment moja inamilikiwa na user mmoja
    // Hakikisha 'user_id' ndiyo column iliyopo kwenye table yako ya payments
    return $this->belongsTo(User::class, 'user_id');
}
}
