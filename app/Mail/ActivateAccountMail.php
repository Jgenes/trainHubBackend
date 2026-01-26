<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ActivateAccountMail extends Mailable
{
    public $activationLink;

    public function __construct($activationLink)
    {
        $this->activationLink = $activationLink;
    }

    public function build()
    {
        return $this->subject('Activate Your Account')
                    ->view('emails.activate-account');
    }
}
