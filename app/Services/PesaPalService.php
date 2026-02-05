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
        $this->baseUrl = config('services.pesapal.url');
    }

    public function getAccessToken()
    {
        $response = Http::post($this->baseUrl . '/api/Auth/RequestToken', [
            'consumer_key'    => $this->key,
            'consumer_secret' => $this->secret,
        ]);

        if ($response->failed()) {
            Log::error('Pesapal Token Error', ['body' => $response->body()]);
            return null;
        }

        return $response->json('token');
    }

    public function getIpnId($token)
    {
        $response = Http::withToken($token)->post($this->baseUrl . '/api/URLSetup/RegisterIPN', [
            'url' => route('pesapal.callback'),
            'ipn_notification_type' => 'POST',
        ]);

        return $response->json('ipn_id');
    }

    public function submitOrder($orderData, $token)
    {
        $response = Http::withToken($token)->post($this->baseUrl . '/api/Transactions/SubmitOrderRequest', $orderData);
        return $response->json();
    }

    public function getTransactionStatus($trackingId, $token)
    {
        $response = Http::withToken($token)->get($this->baseUrl . '/api/Transactions/GetTransactionStatus', [
            'orderTrackingId' => $trackingId,
        ]);
        return $response->json();
    }
}