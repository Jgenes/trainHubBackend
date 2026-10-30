<?php

namespace App\Providers;

use App\Mail\Transport\HttpApiTransport;
use App\Models\Payment;
use App\Observers\PaymentObserver;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Payment::observe(PaymentObserver::class);

        // MAIL_MAILER=http delivers through a transactional email API over
        // HTTPS (Resend, Brevo, SendGrid). Needed on hosts that block
        // outbound SMTP ports, such as Render's free plan.
        Mail::extend('http', fn (array $config) => new HttpApiTransport($config));
    }
}
