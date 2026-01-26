<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PesapalV30Service;

class PesapalController extends Controller
{
    public function pay(Request $request)
    {
        $pesapal = new PesapalV30Service(config('services.pesapal.mode'));

        $token = $pesapal->getAccessToken(
            config('services.pesapal.key'),
            config('services.pesapal.secret')
        )->token;

        $ipn = $pesapal->registerIPN(
            $token,
            route('pesapal.callback')
        );

        $order = [
            "id" => uniqid('ORD-'),
            "currency" => $request->currency,
            "amount" => number_format($request->amount, 2),
            "description" => $request->description,
            "callback_url" => route('pesapal.redirect'),
            "notification_id" => $ipn->ipn_id,
            "billing_address" => [
                "email_address" => $request->email,
                "phone_number" => $request->phone,
                "country_code" => "TZ",
                "first_name" => $request->first_name,
                "last_name" => $request->last_name,
            ]
        ];

        $response = $pesapal->submitOrder($token, $order);

        return view('payments.iframe', [
            'iframe_src' => $response->redirect_url
        ]);
    }

    /**
     * USER REDIRECT (browser)
     * HII NDIYO redirect uliokuwa unauliza iko wapi
     */
    public function redirect(Request $request)
    {
        $orderTrackingId = $request->OrderTrackingId ?? null;
        $merchantRef     = $request->OrderMerchantReference ?? null;

        // Hapa USIBADILISHE chochote kingine kwa sasa
        // unaweza kuonyesha tu status page
        return view('payments.result', [
            'tracking_id' => $orderTrackingId,
            'order_id' => $merchantRef
        ]);
    }

    /**
     * PESAPAL IPN (server → server)
     */
    public function callback(Request $request)
    {
        // Pesapal hutuma hivi
        $orderTrackingId = $request->OrderTrackingId;
        $merchantRef     = $request->OrderMerchantReference;
        $status          = $request->Status; // COMPLETED | FAILED | INVALID

        // Kwa sasa rudisha OK tu (inatosha kufanya iseme successful)
        return response()->json(['status' => 'OK']);
    }
}
