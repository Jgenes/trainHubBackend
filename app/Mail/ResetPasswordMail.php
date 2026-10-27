<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $link,
        public string $token,
    ) {
    }

    public function build()
    {
        return $this->subject('Reset your TrainingHub password')
            ->view('emails.reset-password');
    }
}
