<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Course;

class CourseApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Hongera! Kozi yako imepitishwa na Ipo Hewani',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.course_approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}