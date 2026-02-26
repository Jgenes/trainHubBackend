<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class LoginOtpMail extends Mailable
{
    public $otp;
    public $subjectText;
    public $purpose;

    public function __construct($otp, $subject = null, $purpose = 'login')
    {
        $this->otp = $otp;
        $this->subjectText = $subject ?? 'Your verification code';
        $this->purpose = $purpose;
    }

    public function build()
    {
        return $this->subject($this->subjectText)
                    ->view('emails.login-otp')
                    ->with(['otp' => $this->otp, 'purpose' => $this->purpose]);
    }
}
