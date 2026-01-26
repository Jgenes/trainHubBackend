<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    // Hizi column lazima ziwemo hapa, vinginevyo unapata 500 Error
    protected $fillable = [
    'provider_id',
    'title',
    'category',
    'mode',
    'short_description', // Iwe hivi
    'long_description',  // Iwe hivi
    'learning_outcomes', // Iwe hivi
    'skills',
    'requirements',
    'contents',
        'videos',
        'handouts',
    'status',
    'banner'
];

protected $casts = [
    'learning_outcomes' => 'array', // Iwe hivi
    'skills' => 'array',
    'requirements' => 'array',
    'contents' => 'array',
];
public function provider()
{
    // Hii inamwambia Laravel kuwa provider_id ni ID ya kwenye table ya Users
    return $this->belongsTo(User::class, 'provider_id');
}

public function cohorts()
{
    return $this->hasMany(Cohort::class, 'course_id');
}
public function notes() { return $this->hasMany(Note::class); }
public function videos() { return $this->hasMany(Video::class); }
public function announcements() { return $this->hasMany(Announcement::class); }
public function learningTools() { return $this->hasMany(LearningTool::class); }
public function questions() { return $this->hasMany(Question::class); }
public function reviews() { return $this->hasMany(Review::class); }

}