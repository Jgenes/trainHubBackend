<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    // Lazima iwe hivi kwa sababu migration yako inasema 'course_notes'
    protected $table = 'course_notes'; 
    protected $fillable = ['course_id', 'title', 'file_path'];
}