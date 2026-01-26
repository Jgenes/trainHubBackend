<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cohort extends Model
{
    protected $table = 'cohorts';

    protected $fillable = [
        'course_id',
        'provider_id',
        'intake_name',
        'start_date',
        'end_date',
        'schedule_text',
        'venue',
        'online_link',
        'capacity',
        'seats_taken',
        'price',
        'registration_deadline',
        'status'
    ];
public function course() {
    return $this->belongsTo(Course::class);
}

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function seatsRemaining()
    {
        return $this->capacity - $this->seats_taken;
    }
}
