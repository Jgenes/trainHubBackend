<?php

namespace App\Http\Controllers;

use App\Mail\LoginOtpMail;
use App\Models\Dispute;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\PesaPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(protected PesaPalService $pesapal)
    {
    }

    // ------------------------------------------------------------------
    // Payment OTP step
    // ------------------------------------------------------------------

    public function sendOtp()
    {
        $user = Auth::user();
        $otp  = random_int(100000, 999999);

        Cache::put('payment_otp_'.$user->id, (string) $otp, now()->addMinutes(5));

        try {
            Mail::to($user->email)->send(new LoginOtpMail($otp, 'Your Payment OTP', 'payment'));
        } catch (\Throwable $e) {
            Log::error('Payment OTP email failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not send the verification code.'], 500);
        }

        if (! app()->isProduction()) {
            Log::info("Payment OTP for user {$user->id}: {$otp}");
        }

        return response()->json(['success' => true, 'message' => 'OTP sent to your email']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => ['required', 'digits:6']]);

        $user      = Auth::user();
        $cachedOtp = Cache::get('payment_otp_'.$user->id);

        if ($cachedOtp && hash_equals((string) $cachedOtp, (string) $request->otp)) {
            Cache::forget('payment_otp_'.$user->id);

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid or expired OTP'], 422);
    }

    // ------------------------------------------------------------------
    // PesaPal flow
    // ------------------------------------------------------------------

    public function initiate(Request $request)
    {
        $data = $request->validate([
            'course_id'   => ['required', 'integer', 'exists:courses,id'],
            'cohort_id'   => ['nullable', 'integer', 'exists:cohorts,id'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'phone'       => ['nullable', 'string', 'max:25'],
            'name'        => ['nullable', 'string', 'max:255'],
            'email'       => ['nullable', 'email'],
            'organization'=> ['nullable', 'string', 'max:255'],
            'position'    => ['nullable', 'string', 'max:255'],
            'street'      => ['nullable', 'string', 'max:255'],
            'region'      => ['nullable', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:255'],
            'postal'      => ['nullable', 'string', 'max:50'],
        ]);

        $user   = Auth::user();
        $course = \App\Models\Course::findOrFail($data['course_id']);

        $phone = preg_replace('/\D/', '', $data['phone'] ?? $user->phone ?? '');
        if (str_starts_with($phone, '0')) {
            $phone = '255'.substr($phone, 1);
        }

        $fullName = $data['name'] ?? $user->name;
        $names    = explode(' ', trim($fullName), 2);

        try {
            $payment = DB::transaction(function () use ($data, $user, $course, $phone, $names, $fullName) {
                $payment = Payment::create([
                    'reference'    => 'TRH-'.strtoupper(Str::random(12)),
                    'user_id'      => $user->id,
                    'course_id'    => $course->id,
                    'cohort_id'    => $data['cohort_id'] ?? null,
                    'first_name'   => $names[0] ?? $user->name,
                    'last_name'    => $names[1] ?? '',
                    'email'        => $data['email'] ?? $user->email,
                    'phone_number' => $phone,
                    'amount'       => $data['amount'],
                    'currency'     => 'TZS',
                    'description'  => 'Payment for '.$course->title,
                    'status'       => 'PENDING',
                    'user_name'    => $fullName,
                    'organization' => $data['organization'] ?? null,
                    'position'     => $data['position'] ?? null,
                    'street'       => $data['street'] ?? null,
                    'region'       => $data['region'] ?? null,
                    'city'         => $data['city'] ?? null,
                    'postal'       => $data['postal'] ?? null,
                    'expires_at'   => now()->addDays(7),
                    'payment_method' => 'Mobile Money / Card',
                ]);

                Invoice::create([
                    'payment_id'     => $payment->id,
                    'invoice_number' => 'INV-'.strtoupper(Str::random(10)),
                    'amount'         => $payment->amount,
                    'status'         => 'PENDING',
                ]);

                return $payment;
            });
        } catch (\Throwable $e) {
            Log::error('Payment creation error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not create the payment record.'], 500);
        }

        if (! config('services.pesapal.key') || ! config('services.pesapal.secret')) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway is not configured. Please contact the administrator.',
            ], 503);
        }

        try {
            $token = $this->pesapal->getAccessToken();

            if (! $token) {
                return response()->json(['success' => false, 'message' => 'Payment gateway authentication failed.'], 502);
            }

            $ipnId = $this->pesapal->getIpnId($token);

            $orderData = [
                'id'              => $payment->reference,
                'currency'        => 'TZS',
                'amount'          => (float) $payment->amount,
                'description'     => 'Payment for '.$course->title,
                'callback_url'    => route('pesapal.callback'),
                'notification_id' => $ipnId,
                'billing_address' => [
                    'email_address' => $payment->email,
                    'phone_number'  => $payment->phone_number,
                    'first_name'    => $payment->first_name,
                    'last_name'     => $payment->last_name ?: $payment->first_name,
                    'country_code'  => 'TZ',
                ],
            ];

            $result = $this->pesapal->submitOrder($orderData, $token);

            if (! empty($result['order_tracking_id']) && ! empty($result['redirect_url'])) {
                $payment->update(['tracking_id' => $result['order_tracking_id']]);

                return response()->json([
                    'success'      => true,
                    'redirect_url' => $result['redirect_url'],
                    'reference'    => $payment->reference,
                ]);
            }

            Log::error('PesaPal submit order failed', ['response' => $result]);

            return response()->json(['success' => false, 'message' => 'Payment gateway rejected the order.'], 502);
        } catch (\Throwable $e) {
            Log::error('Payment initiation error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Payment gateway is temporarily unavailable.'], 502);
        }
    }

    /** Browser return + IPN from PesaPal. */
    public function callback(Request $request)
    {
        $trackingId = $request->input('OrderTrackingId')
            ?? $request->query('OrderTrackingId')
            ?? $request->input('orderTrackingId');

        $status = 'PENDING';

        if ($trackingId) {
            try {
                $token = $this->pesapal->getAccessToken();

                if ($token) {
                    $response = $this->pesapal->getTransactionStatus($trackingId, $token);
                    $status   = strtoupper($response['payment_status_description'] ?? 'PENDING');

                    // PesaPal returns "Completed"/"Failed"/"Invalid".
                    $status = match ($status) {
                        'COMPLETED', 'SUCCESS' => 'COMPLETED',
                        'FAILED', 'REVERSED'   => 'FAILED',
                        'INVALID'              => 'INVALID',
                        default                => 'PENDING',
                    };
                }

                $payment = Payment::where('tracking_id', $trackingId)->first();

                if ($payment && $status !== 'PENDING') {
                    DB::transaction(function () use ($payment, $status, $trackingId) {
                        $payment->update(['status' => $status]);

                        if ($payment->isPaid()) {
                            Receipt::firstOrCreate(
                                ['payment_id' => $payment->id],
                                [
                                    'receipt_number'        => 'REC-'.strtoupper(\Illuminate\Support\Str::random(10)),
                                    'amount'                => $payment->amount,
                                    'transaction_reference' => $trackingId,
                                ]
                            );

                            $payment->invoice()?->update(['status' => 'PAID']);
                        } elseif (in_array($status, ['FAILED', 'INVALID'], true)) {
                            Dispute::firstOrCreate(
                                ['payment_id' => $payment->id, 'reason' => 'Transaction '.$status],
                                ['status' => 'OPEN']
                            );
                        }
                    });
                }
            } catch (\Throwable $e) {
                Log::error('PesaPal callback error: '.$e->getMessage());
            }
        }

        $query = http_build_query([
            'status'    => $status,
            'reference' => $request->input('OrderMerchantReference', ''),
        ]);

        return redirect()->away(config('app.frontend_url').'/payment-success?'.$query);
    }

    public function adminFinancials()
    {
        return response()->json([
            'success'  => true,
            'invoices' => Invoice::with('payment.user')->latest()->get(),
            'receipts' => Receipt::with('payment.user')->latest()->get(),
            'disputes' => Dispute::with('payment.user')->latest()->get(),
        ]);
    }
}
