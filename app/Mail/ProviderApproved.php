<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProviderApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $provider;

    /**
     * Create a new message instance.
     */
    public function __construct($provider)
    {
        $this->provider = $provider;
    }

    /**
     * Envelope (subject of email)
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Hongera! Akaunti Yako Imepitishwa',
        );
    }

    /**
     * Content of email (Blade template)
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.provider_approved',
            with: [
                'name' => $this->provider->legal_name,
                'login_url' => url('/login'),
            ],
        );
    }
}
