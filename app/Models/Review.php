<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'reviews'; // Hakikisha jina la table ni hili
    protected $guarded = []; // Hii inaruhusu data zote kuingia bila kuzuiliwa
    public function user()
{
    return $this->belongsTo(User::class);
}

public function course()
{
    return $this->belongsTo(Course::class);
}
}