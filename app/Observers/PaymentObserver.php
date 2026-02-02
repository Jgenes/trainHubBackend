<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\Enrollment;

class PaymentObserver
{
    public function updated(Payment $payment): void
    {
        // 1. Angalia kama status imebadilika kuwa 'COMPLETED' au 'PAID'
        // Na hakikisha enrollment haipo tayari (kuepuka duplicates)
        if ($payment->status === 'COMPLETED' || $payment->status === 'PAID') {
            
            $exists = Enrollment::where('user_id', $payment->user_id)
                                ->where('cohort_id', $payment->course_id)
                                ->exists();

            if (!$exists) {
                // 2. Insert data automatic kwenye enrollment
                Enrollment::create([
                    'user_id'   => $payment->user_id,
                    'cohort_id' => $payment->course_id, // Chukua course_id toka payments
                    'amount'    => $payment->amount,
                    'status'    => 'PAID',
                ]);
            }
        }
    }
}