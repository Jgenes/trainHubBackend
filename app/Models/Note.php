<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $table = 'course_notes';
    protected $fillable = ['course_id', 'title', 'file_path'];
}
