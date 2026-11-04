<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Support\EnrollmentFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Legacy, non-PesaPal checkout used by the older React flow. It creates a
 * pending payment. Confirmation requires a separately verified payment.
 */
class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'cohort_id' => ['nullable', 'integer', 'exists:cohorts,id'],
            'amount'    => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

        $decision = EnrollmentFlow::decision($user->id, $data['course_id']);
        if ($decision['is_enrolled']) {
            return response()->json(array_merge([
                'message' => 'You are already enrolled in this course.',
            ], $decision), 409);
        }


        $payment = DB::transaction(function () use ($data, $user) {
            $names = explode(' ', trim($user->name), 2);

            return Payment::create([
                'reference'    => 'TRH-'.strtoupper(Str::random(12)),
                'user_id'      => $user->id,
                'course_id'    => $data['course_id'],
                'cohort_id'    => $data['cohort_id'] ?? null,
                'first_name'   => $names[0],
                'last_name'    => $names[1] ?? '',
                'email'        => $user->email,
                'phone_number' => $user->phone,
                'amount'       => $data['amount'],
                'currency'     => 'TZS',
                'status'       => 'PENDING',
                'user_name'    => $user->name,
                'description'  => 'Offline / manual checkout',
            ]);
        });

        return response()->json([
            'success'       => true,
            'enrollment_id' => $payment->id,
            'reference'     => $payment->reference,
            'payment'       => $payment,
        ], 201);
    }

    public function confirm($enrollmentId)
    {
        $payment = Payment::where('id', $enrollmentId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // A learner's browser is not proof of payment. Only the gateway or
        // authorized provider payment-status flow may mark a payment paid.
        if (! $payment->isPaid()) {
            return response()->json(['message' => 'Payment has not been verified.'], 409);
        }

        DB::transaction(function () use ($payment) {

            Enrollment::updateOrCreate(
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
        });

        return response()->json(['success' => true, 'message' => 'Enrollment confirmed.']);
    }
}
