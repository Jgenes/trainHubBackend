<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PesaPalService;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Invoice; // Imeongezwa
use App\Models\Receipt; // Imeongezwa
use App\Models\Dispute; // Imeongezwa
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\LoginOtpMail;

class PaymentController extends Controller
{
    protected $pesapal;

    public function __construct(PesaPalService $pesapal)
    {
        $this->pesapal = $pesapal;
    }

    public function sendOtp(Request $request) {
        $user = auth()->user();
        $otp = rand(100000, 999999);
        Cache::put('payment_otp_' . $user->id, $otp, now()->addMinutes(5));

        try {
            Mail::to($user->email)->queue(new LoginOtpMail($otp, 'Your Payment OTP', 'payment'));
        } catch (\Exception $e) {
            Log::error('Failed to send payment OTP email: ' . $e->getMessage());
        }

        Log::info("OTP for User {$user->id}: {$otp}");
        return response()->json(['success' => true, 'message' => 'OTP sent to your email']);
    }

    public function resendOtp(Request $request)
    {
        $user = auth()->user();
        $otp = rand(100000, 999999);
        Cache::put('payment_otp_' . $user->id, $otp, now()->addMinutes(5));

        try {
            Mail::to($user->email)->queue(new LoginOtpMail($otp, 'Your Payment OTP', 'payment'));
        } catch (\Exception $e) {
            Log::error('Failed to resend payment OTP email: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send OTP'], 500);
        }

        Log::info("Resent payment OTP for User {$user->id}: {$otp}");
        return response()->json(['success' => true, 'message' => 'OTP resent to your email']);
    }

    public function verifyOtp(Request $request) {
        $user = auth()->user();
        $cachedOtp = Cache::get('payment_otp_' . $user->id) ?? Cache::get('otp_' . $user->id);

        if ($request->otp == $cachedOtp) {
            Cache::forget('payment_otp_' . $user->id);
            Cache::forget('otp_' . $user->id);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid or expired OTP'], 422);
    }

    public function initiate(Request $request)
    {
        try {
            $user = auth()->user();
            $course = \App\Models\Course::findOrFail($request->course_id);

            $phone = preg_replace('/\D/', '', $request->phone ?? $user->phone_number);
            if (str_starts_with($phone, '0')) { $phone = '255' . substr($phone, 1); }

            // 1. Create Local Record
            $payment = Payment::create([
                'reference'     => 'TRH-' . strtoupper(uniqid()),
                'user_id'       => $user->id,
                'course_id'     => $course->id,
                'cohort_id'     => $request->cohort_id, 
                'first_name'    => $user->name, 
                'email'         => $user->email,
                'phone_number'  => $phone,
                'amount'        => (float)$request->amount,
                'currency'      => 'TZS',
                'status'        => 'PENDING',
            ]);

            // MABADILIKO: Insert data into Invoice table after status being PENDING
            if ($payment->status === 'PENDING') {
                Invoice::create([
                    'payment_id' => $payment->id,
                    'invoice_no' => 'INV-' . time(),
                    'amount' => $payment->amount,
                ]);

                // Send email to admin
                try {
                    Mail::raw("New Pending Payment: {$payment->reference}", function($message) {
                        $message->to('josephgenes1999@gmail.com')->subject('New Invoice Generated');
                    });
                } catch (\Exception $e) {
                    Log::error("Admin email failed: " . $e->getMessage());
                }
            }

            // 2. PesaPal Flow
            $token = $this->pesapal->getAccessToken();
            if (!$token) return response()->json(['success' => false, 'message' => 'Auth Token Failed'], 500);

            $ipnId = $this->pesapal->getIpnId($token);

            $orderData = [
                "id"               => $payment->reference,
                "currency"         => "TZS",
                "amount"           => (float)$payment->amount,
                "description"      => "Payment for " . $course->title,
                "callback_url"     => "http://localhost:3000/dashboard/my-courses",
                "notification_id"  => $ipnId,
                "billing_address"  => [
                    "email_address" => $payment->email,
                    "phone_number"  => $payment->phone_number,
                    "first_name"    => $payment->first_name,
                    "country_code"  => "TZ",
                ]
            ];

            $result = $this->pesapal->submitOrder($orderData, $token);

            if (isset($result['order_tracking_id'])) {
                $payment->update(['tracking_id' => $result['order_tracking_id']]);
                return response()->json(['success' => true, 'redirect_url' => $result['redirect_url']]);
            }

            return response()->json(['success' => false, 'message' => 'PesaPal Submission Failed'], 500);

        } catch (\Exception $e) {
            Log::error("Payment Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function callback(Request $request)
    {
        $trackingId = $request->query('OrderTrackingId');
        $token = $this->pesapal->getAccessToken();
        $statusResponse = $this->pesapal->getTransactionStatus($trackingId, $token);

        $payment = Payment::where('tracking_id', $trackingId)->first();
        
        if ($payment) {
            $status = strtoupper($statusResponse['payment_status_description'] ?? 'PENDING');
            
            // Update status ya payment
            $payment->update(['status' => $status]);

            // MABADILIKO: Kama status ni COMPLETED
            if (in_array($status, ['COMPLETED', 'PAID'])) {
                
                // 1. Insert data to Receipt table
                Receipt::create([
                    'payment_id' => $payment->id,
                    'receipt_no' => 'REC-' . time(),
                    'amount' => $payment->amount
                ]);

                // 2. Send email to admin
                try {
                    Mail::raw("Payment Success: {$payment->reference}", function($message) {
                        $message->to('josephgenes1999@gmail.com')->subject('Payment Successful');
                    });
                } catch (\Exception $e) {
                    Log::error("Admin success email failed: " . $e->getMessage());
                }

                // 3. Mpe kozi (Code yako ya awali)
                Enrollment::firstOrCreate([
                    'user_id' => $payment->user_id,
                    'cohort_id' => $payment->cohort_id
                ], [
                    'amount' => $payment->amount,
                    'status' => 'PAID'
                ]);
            }

            // MABADILIKO: Kama ikifeli ingiza kwenye Dispute
            if (in_array($status, ['FAILED', 'REVERSED'])) {
                Dispute::create([
                    'payment_id' => $payment->id,
                    'reason' => 'Transaction ' . $status,
                ]);
            }
        }

        return redirect()->away(env('FRONTEND_URL', 'http://localhost:5173/') . '/payment-success?status=' . $status);
    }

// App/Http/Controllers/PaymentController.php

public function adminFinancials()
{
    // Hakikisha anayetafuta ni Admin
    if (auth()->user()->role !== 'admin') {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    return response()->json([
        'success' => true,
        'invoices' => Invoice::with('payment.user')->latest()->get(),
        'receipts' => Receipt::with('payment.user')->latest()->get(),
        'disputes' => Dispute::with('payment.user')->latest()->get(),
    ]);
}


}