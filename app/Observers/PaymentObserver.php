<?php

namespace App\Observers;

use App\Models\Enrollment;
use App\Models\Payment;

class PaymentObserver
{
    /** Grant course access as soon as a payment becomes successful. */
    public function updated(Payment $payment): void
    {
        if (! $payment->isPaid()) {
            return;
        }

        Enrollment::firstOrCreate(
            [
                'user_id'   => $payment->user_id,
                'course_id' => $payment->course_id,
                'cohort_id' => $payment->cohort_id,
            ],
            [
                'status'      => 'PAID',
                'amount'      => $payment->amount,
                'provider_id' => $payment->course?->provider_id,
            ]
        );
    }
}
