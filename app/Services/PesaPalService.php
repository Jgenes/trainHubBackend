<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PesaPalService
{
    protected $baseUrl;
    protected $key;
    protected $secret;

    public function __construct()
    {
        $this->key = config('services.pesapal.key');
        $this->secret = config('services.pesapal.secret');

        $this->baseUrl = config('services.pesapal.mode') === 'live'
            ? 'https://pay.pesapal.com/v3'
            : 'https://cybqa.pesapal.com/pesapalv3';
    }

    /**
     * Get Access Token
     */
    public function getAccessToken()
    {
        $response = Http::withOptions([
            'verify' => false, // ❌ DEV ONLY
        ])->post($this->baseUrl . '/api/Auth/RequestToken', [
            'consumer_key'    => $this->key,
            'consumer_secret' => $this->secret,
        ]);

        if ($response->failed()) {
            Log::error('Pesapal Token Error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        return $response->json('token');
    }

    /**
     * Register IPN
     */
    public function getIpnId($token)
    {
        $response = Http::withOptions([
            'verify' => false, // ❌ DEV ONLY
        ])->withToken($token)->post(
            $this->baseUrl . '/api/URLSetup/RegisterIPN',
            [
                'url' => route('pesapal.callback'),
                'ipn_notification_type' => 'POST',
            ]
        );

        if ($response->failed()) {
            Log::error('Pesapal IPN Error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        return $response->json('ipn_id');
    }

    /**
     * Submit Order
     */
    public function submitOrder($orderData, $token)
    {
        $response = Http::withOptions([
            'verify' => false, // ❌ DEV ONLY
        ])->withToken($token)->post(
            $this->baseUrl . '/api/Transactions/SubmitOrderRequest',
            $orderData
        );

        if ($response->failed()) {
            Log::error('Pesapal Submit Order Error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return $response->json();
    }

    /**
     * Get Transaction Status
     */
    public function getTransactionStatus($trackingId, $token)
    {
        $response = Http::withOptions([
            'verify' => false, // ❌ DEV ONLY
        ])->withToken($token)->get(
            $this->baseUrl . '/api/Transactions/GetTransactionStatus',
            [
                'orderTrackingId' => $trackingId,
            ]
        );

        if ($response->failed()) {
            Log::error('Pesapal Status Error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return $response->json();
    }
}
