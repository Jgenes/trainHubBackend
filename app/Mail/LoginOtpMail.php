<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class LoginOtpMail extends Mailable
{
    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('Your Login OTP')
                    ->view('emails.login-otp');
    }
}
