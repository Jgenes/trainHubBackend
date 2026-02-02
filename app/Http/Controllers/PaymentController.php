<?php
    namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PesaPalService;
use App\Models\Payment;
use App\Models\Enrollment; // Hakikisha umei-import
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
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Login kwanza'], 401);
            }

            $course = \App\Models\Course::findOrFail($request->course_id);

            // 1. Tengeneza Payment Record
            $payment = Payment::create([
                'reference'     => 'TRH-' . strtoupper(uniqid()),
                'user_id'       => $user->id,
                'course_id'     => $course->id,
                'cohort_id'     => $request->cohort_id, 
                'first_name'    => $request->name ?? $user->name, 
                'email'         => $request->email ?? $user->email,
                'phone_number'  => $request->phone ?? $user->phone_number,
                'amount'        => (float)($request->amount ?? $course->price ?? 0),
                'currency'      => 'TZS',
                'status'        => 'PENDING',
            ]);

            $token = $this->pesapal->getAccessToken();
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

            // 2. MUHIMU: Hifadhi tracking_id inayotoka PesaPal
            if (isset($result['order_tracking_id'])) {
                $payment->update(['tracking_id' => $result['order_tracking_id']]);
            }

            if (isset($result['redirect_url'])) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $result['redirect_url']
                ]);
            }

            return response()->json(['success' => false, 'message' => 'PesaPal Connection Failed'], 500);

        } catch (\Exception $e) {
            Log::error("Payment Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function callback(Request $request)
    {
        $trackingId = $request->query('OrderTrackingId');
        if (!$trackingId) return response()->json(['message' => 'No tracking ID'], 400);

        $token = $this->pesapal->getAccessToken();
        $statusResponse = $this->pesapal->getTransactionStatus($trackingId, $token);

        // 3. Tafuta Payment kwa trackingId
        $payment = Payment::where('tracking_id', $trackingId)->first();

        if ($payment) {
            $pesapalStatus = $statusResponse['payment_status_description'] ?? 'UNKNOWN';

            // 4. Update Status ya Payment
            $payment->update(['status' => $pesapalStatus]);

            // 5. AUTOMATIC ENROLLMENT LOGIC
            // PesaPal status ya mafanikio ni "Completed"
            if (strtoupper($pesapalStatus) === 'COMPLETED' || strtoupper($pesapalStatus) === 'PAID') {
                
                // Zuia duplicate enrollment
                $exists = Enrollment::where('user_id', $payment->user_id)
                                    ->where('cohort_id', $payment->cohort_id)
                                    ->exists();

                if (!$exists) {
                    Enrollment::create([
                        'user_id'   => $payment->user_id,
                        'cohort_id' => $payment->cohort_id,
                        'amount'    => $payment->amount,
                        'status'    => 'PAID',
                    ]);
                    Log::info("Enrollment successful for User: " . $payment->user_id);
                }
            }
        }

        // Kwa API, rudisha JSON. Kwa Web, rudisha View.
        return response()->json([
            'status' => $pesapalStatus,
            'payment' => $payment
        ]);
    }
}