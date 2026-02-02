<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment; // Hakikisha hii ipo
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;
    public $pdf;

    /**
     * Tunapokea data za malipo na PDF kutoka kwenye Controller
     */
    public function __construct($payment, $pdf)
    {
        $this->payment = $payment;
        $this->pdf = $pdf;
    }

    /**
     * Kichwa cha barua pepe (Subject)
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Risiti ya Malipo - ' . $this->payment->reference,
        );
    }

    /**
     * Muonekano wa barua pepe (Body text)
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment_success', // Hakikisha unatengeneza view hii
        );
    }

    /**
     * Hapa ndipo tunapoambatanisha (Attach) ile risiti ya PDF
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf->output(), 'Receipt-' . $this->payment->reference . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}