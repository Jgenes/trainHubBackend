<?php

namespace App\Mail;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CourseDrafted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Course $course,
        public ?string $reason = null,
    ) {
    }

    public function build()
    {
        return $this->subject('Taarifa: Kozi yako imerudishwa kwenye Draft')
            ->view('emails.course_drafted');
    }
}
