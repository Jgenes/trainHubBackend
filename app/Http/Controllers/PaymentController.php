<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PesaPalService;
use App\Models\Payment;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $pesapal;

    public function __construct(PesaPalService $pesapal)
    {
        $this->pesapal = $pesapal;
    }

    public function initiate(Request $request)
    {
        try {
            $user = auth()->user();
            $course = \App\Models\Course::findOrFail($request->course_id);

            // Safisha namba ya simu
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

        // Kama malipo yamekamilika, mpe kozi
        if (in_array($status, ['COMPLETED', 'PAID'])) {
            Enrollment::firstOrCreate([
                'user_id' => $payment->user_id,
                'cohort_id' => $payment->cohort_id
            ], [
                'amount' => $payment->amount,
                'status' => 'PAID'
            ]);
        }
    }

    // BADALA YA JSON: Mpeleke mteja kwenye Success Page ya React
    // Hakikisha umeandika link kamili ya frontend yako
    return redirect()->away(env('FRONTEND_URL', 'http://localhost:5173/') . '/payment-success?status=' . $status);
}
}