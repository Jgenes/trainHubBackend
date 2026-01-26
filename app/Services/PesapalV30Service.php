<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PesapalV30Service
{
    protected $baseUrl;

    public function __construct($mode = 'demo')
    {
        $this->baseUrl = $mode === 'live'
            ? 'https://pay.pesapal.com/v3'
            : 'https://cybqa.pesapal.com/pesapalv3';
    }

    public function getAccessToken($key, $secret)
    {
        $response = Http::post(
            $this->baseUrl.'/api/Auth/RequestToken',
            [
                'consumer_key'    => $key,
                'consumer_secret' => $secret,
            ]
        );

        return $response->object();
    }

    public function registerIPN($token, $callbackUrl)
    {
        return Http::withToken($token)->post(
            $this->baseUrl.'/api/URLSetup/RegisterIPN',
            [
                'url' => $callbackUrl,
                'ipn_notification_type' => 'POST'
            ]
        )->object();
    }

    public function submitOrder($token, $payload)
    {
        return Http::withToken($token)->post(
            $this->baseUrl.'/api/Transactions/SubmitOrderRequest',
            $payload
        )->object();
    }

    public function transactionStatus($token, $orderTrackingId)
    {
        return Http::withToken($token)->get(
            $this->baseUrl.'/api/Transactions/GetTransactionStatus',
            ['orderTrackingId' => $orderTrackingId]
        )->object();
    }
}
