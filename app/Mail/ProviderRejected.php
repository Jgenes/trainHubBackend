<?php

namespace App\Mail;

use App\Models\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProviderRejected extends Mailable
{
    use Queueable, SerializesModels;

    public $provider;
    public $reason;

    /**
     * Create a new message instance.
     *
     * @param Provider $provider
     * @param string $reason
     */
    public function __construct(Provider $provider, $reason)
    {
        $this->provider = $provider;
        $this->reason = $reason;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Taarifa: Maombi ya Kujiunga na Huduma Yamekataliwa')
                    ->view('emails.provider_rejected')
                    ->with([
                        'providerName' => $this->provider->legal_name,
                        'contactPerson' => $this->provider->contact_name,
                        'rejectionReason' => $this->reason,
                    ]);
    }
}