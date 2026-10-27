<?php

namespace App\Http\Controllers;

use App\Services\PesaPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Legacy browser-based PesaPal flow kept for the /pesapal pages. */
class PesapalController extends Controller
{
    public function __construct(protected PesaPalService $pesapal)
    {
    }

    public function pay(Request $request)
    {
        $data = $request->validate([
            'amount'      => ['required', 'numeric', 'min:1'],
            'currency'    => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:255'],
            'email'       => ['required', 'email'],
            'phone'       => ['nullable', 'string', 'max:25'],
            'first_name'  => ['required', 'string', 'max:255'],
            'last_name'   => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $token = $this->pesapal->getAccessToken();

            if (! $token) {
                return response()->json(['message' => 'Payment gateway authentication failed.'], 502);
            }

            $ipnId = $this->pesapal->getIpnId($token);

            $order = [
                'id'              => 'ORD-'.strtoupper(bin2hex(random_bytes(6))),
                'currency'        => $data['currency'] ?? 'TZS',
                'amount'          => (float) $data['amount'],
                'description'     => $data['description'] ?? 'TrainingHub payment',
                'callback_url'    => route('pesapal.web.redirect'),
                'notification_id' => $ipnId,
                'billing_address' => [
                    'email_address' => $data['email'],
                    'phone_number'  => $data['phone'] ?? '',
                    'country_code'  => 'TZ',
                    'first_name'    => $data['first_name'],
                    'last_name'     => $data['last_name'] ?? $data['first_name'],
                ],
            ];

            $response = $this->pesapal->submitOrder($order, $token);

            if (empty($response['redirect_url'])) {
                return response()->json(['message' => 'Payment gateway rejected the order.'], 502);
            }

            return view('payments.iframe', ['iframe_src' => $response['redirect_url']]);
        } catch (\Throwable $e) {
            Log::error('Legacy PesaPal pay error: '.$e->getMessage());

            return response()->json(['message' => 'Payment gateway is temporarily unavailable.'], 502);
        }
    }

    public function redirect(Request $request)
    {
        return view('payments.result', [
            'tracking_id' => $request->input('OrderTrackingId'),
            'order_id'    => $request->input('OrderMerchantReference'),
        ]);
    }

    public function callback(Request $request)
    {
        // The authoritative status update happens on /api/pesapal/callback.
        return response()->json([
            'order_notification_type' => $request->input('OrderNotificationType'),
            'order_tracking_id'       => $request->input('OrderTrackingId'),
            'order_merchant_reference'=> $request->input('OrderMerchantReference'),
            'status'                  => 'OK',
        ]);
    }
}
