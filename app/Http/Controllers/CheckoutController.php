<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Legacy, non-PesaPal checkout used by the older React flow. It creates a
 * pending payment; access is granted only once the gateway confirms it.
 */
class CheckoutController extends Controller
{
    public function __construct(protected PaymentVerifier $verifier)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'cohort_id' => ['nullable', 'integer', 'exists:cohorts,id'],
            'amount'    => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

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

    /**
     * Finish a checkout.
     *
     * This endpoint must never grant access on its own: it used to write
     * status = COMPLETED straight from the browser, so merely reaching this
     * URL (for example right after clicking "Enrol") made the catalogue report
     * `is_enrolled: true` while the payment was still unpaid — which is what
     * sent learners to "My learning" instead of checkout. Now it only asks
     * PesaPal to confirm the transaction and reports back.
     */
    public function confirm($enrollmentId)
    {
        $payment = Payment::where('id', $enrollmentId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Already settled: reply idempotently without touching the gateway.
        if ($payment->isVerifiedPaid()) {
            $payment->ensureEnrollment();

            return response()->json([
                'success'     => true,
                'is_enrolled' => true,
                'message'     => 'Enrollment confirmed.',
            ]);
        }

        $gatewayStatus = $this->verifier->settle($payment);

        if ($gatewayStatus === null) {
            return response()->json([
                'success'     => false,
                'is_enrolled' => false,
                'status'      => strtoupper((string) $payment->fresh()->status),
                'message'     => 'This payment cannot be confirmed yet. Access is granted automatically as soon as PesaPal confirms the transaction.',
            ], 409);
        }

        $payment->refresh();

        if (! $payment->isVerifiedPaid()) {
            return response()->json([
                'success'     => false,
                'is_enrolled' => false,
                'status'      => $gatewayStatus,
                'message'     => 'PesaPal has not completed this transaction yet.',
            ], 409);
        }

        try {
            $payment->ensureEnrollment();
        } catch (\Throwable $e) {
            Log::error('Enrollment creation failed: '.$e->getMessage());
        }

        return response()->json([
            'success'     => true,
            'is_enrolled' => true,
            'message'     => 'Enrollment confirmed.',
        ]);
    }
}
