<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['course_id', 'title', 'content', 'file_path'];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
