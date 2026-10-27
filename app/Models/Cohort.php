<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cohort extends Model
{
    protected $table = 'cohorts';

    protected $fillable = [
        'course_id', 'provider_id', 'intake_name', 'start_date', 'end_date',
        'schedule_text', 'venue', 'online_link', 'capacity', 'seats_taken',
        'price', 'registration_deadline', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date'            => 'date',
            'end_date'              => 'date',
            'registration_deadline' => 'date',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'cohort_id');
    }

    public function seatsRemaining(): int
    {
        return max(0, (int) $this->capacity - (int) $this->seats_taken);
    }

    /** Recompute OPEN / FULL / CLOSED from capacity and dates. */
    public function refreshStatus(): string
    {
        $today = now()->startOfDay();

        if ($this->end_date && $today->greaterThan($this->end_date)) {
            $this->status = 'CLOSED';
        } elseif ($this->seatsRemaining() <= 0) {
            $this->status = 'FULL';
        } elseif ($this->status === 'FULL' && $this->seatsRemaining() > 0) {
            $this->status = 'OPEN';
        }

        $this->save();

        return $this->status;
    }
}
