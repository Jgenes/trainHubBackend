<?php

namespace App\Observers;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the `enrollments` mirror in sync — but only for confirmed payments.
 *
 * A row written as COMPLETED without a `verified_at` timestamp did not come
 * from the PesaPal callback, and granting access from it is exactly the bug
 * that sent learners to "My learning" before they had paid. Such a row is
 * logged and ignored; only PaymentVerifier may set the timestamp.
 */
class PaymentObserver
{
    public function created(Payment $payment): void
    {
        // Rows that arrive already confirmed (imports, admin fixes) must be
        // mirrored too, so "verified paid => enrollment exists" always holds.
        if ($payment->isVerifiedPaid()) {
            $payment->ensureEnrollment();
        }
    }

    public function updated(Payment $payment): void
    {
        if (! $payment->isPaid()) {
            return;
        }

        if (! $payment->isVerifiedPaid()) {
            Log::warning('Payment marked paid without gateway verification; course access not granted.', [
                'payment_id' => $payment->id,
                'reference'  => $payment->reference,
                'status'     => $payment->status,
            ]);

            return;
        }

        $payment->ensureEnrollment();
    }
}
