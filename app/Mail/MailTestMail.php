<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/**
 * Message sent by `php artisan mail:diagnose --to=you@example.com` and by
 * `GET /api/mail-check?token=…&send=you@example.com`.
 *
 * It exists so the diagnostic can prove end-to-end delivery instead of only
 * checking that a socket opens.
 */
class MailTestMail extends Mailable
{
    public function build()
    {
        return $this->subject('Training Hub mail delivery test')
                    ->view('emails.mail-test')
                    ->with([
                        'timestamp' => now()->toDateTimeString(),
                        'mailer'    => config('mail.default'),
                    ]);
    }
}
