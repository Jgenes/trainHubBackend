<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'provider_id', 'title', 'category', 'mode',
        'short_description', 'long_description', 'learning_outcomes',
        'skills', 'requirements', 'contents', 'videos', 'handouts',
        'status', 'banner',
    ];

    protected function casts(): array
    {
        return [
            'learning_outcomes' => 'array',
            'skills'            => 'array',
            'requirements'      => 'array',
            'contents'          => 'array',
            'videos'            => 'array',
            'handouts'          => 'array',
        ];
    }

    /** Canonical DB values are Draft / Published / Closed. */
    public static function normalizeStatus(?string $status): string
    {
        return match (strtoupper(trim((string) $status))) {
            'PUBLISHED', 'PUBLISH', 'APPROVED', 'ACTIVE' => 'Published',
            'DRAFT', 'PENDING', 'REJECTED'               => 'Draft',
            'CLOSED', 'SUSPENDED'                        => 'Closed',
            default                                      => 'Draft',
        };
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'Published');
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function cohorts()
    {
        return $this->hasMany(Cohort::class, 'course_id');
    }

    public function notes()          { return $this->hasMany(Note::class); }
    public function videos()         { return $this->hasMany(Video::class); }
    public function announcements()  { return $this->hasMany(Announcement::class); }
    public function learningTools()  { return $this->hasMany(LearningTool::class); }
    public function questions()      { return $this->hasMany(Question::class); }
    public function reviews()        { return $this->hasMany(Review::class); }
    public function enrollments()    { return $this->hasMany(Enrollment::class, 'course_id'); }
}
