<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Enrollment extends Model
{
    protected $fillable = [
        'id', 'student_id', 'provider_id', 'course_id', 'cohort_id', 'status', 'expires_at'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    public function cohort() { return $this->belongsTo(Cohort::class); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function invoice() { return $this->hasOne(Invoice::class); }
}
