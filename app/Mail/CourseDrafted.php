<?php

namespace App\Mail;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CourseDrafted extends Mailable
{
    use Queueable, SerializesModels;

    public $course;
    public $reason; 
    public function __construct(Course $course)
    {
        $this->course = $course;
    }

    public function build()
    {
        return $this->subject('Taarifa: Kozi yako imerudishwa kwenye Draft')
                    ->view('emails.course_drafted');
    }
}