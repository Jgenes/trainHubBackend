<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningTool extends Model
{
    protected $fillable = ['course_id', 'title', 'link', 'description'];
}