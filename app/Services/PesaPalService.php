<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PesaPalService
{
    protected ?string $baseUrl;
    protected ?string $key;
    protected ?string $secret;

    public function __construct()
    {
        $this->key     = config('services.pesapal.key');
        $this->secret  = config('services.pesapal.secret');
        $this->baseUrl = rtrim((string) config('services.pesapal.url'), '/');
    }

    public function getAccessToken(): ?string
    {
        try {
            $response = Http::asJson()->timeout(20)->post($this->baseUrl.'/api/Auth/RequestToken', [
                'consumer_key'    => $this->key,
                'consumer_secret' => $this->secret,
            ]);

            if ($response->failed()) {
                Log::error('PesaPal token error', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            return $response->json('token');
        } catch (\Throwable $e) {
            Log::error('PesaPal token exception: '.$e->getMessage());
            return null;
        }
    }

    public function getIpnId(?string $token): ?string
    {
        if (! $token) {
            return null;
        }

        try {
            $response = Http::withToken($token)->asJson()->timeout(20)
                ->post($this->baseUrl.'/api/URLSetup/RegisterIPN', [
                    'url'                   => route('pesapal.callback'),
                    'ipn_notification_type' => 'POST',
                ]);

            if ($response->failed()) {
                Log::error('PesaPal IPN registration failed', ['body' => $response->body()]);
                return null;
            }

            return $response->json('ipn_id');
        } catch (\Throwable $e) {
            Log::error('PesaPal IPN exception: '.$e->getMessage());
            return null;
        }
    }

    public function submitOrder(array $orderData, string $token): array
    {
        try {
            $response = Http::withToken($token)->asJson()->timeout(20)
                ->post($this->baseUrl.'/api/Transactions/SubmitOrderRequest', $orderData);

            if ($response->failed()) {
                Log::error('PesaPal submit order failed', ['status' => $response->status(), 'body' => $response->body()]);
                return [];
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('PesaPal submit order exception: '.$e->getMessage());
            return [];
        }
    }

    public function getTransactionStatus(string $trackingId, string $token): array
    {
        try {
            $response = Http::withToken($token)->timeout(20)
                ->get($this->baseUrl.'/api/Transactions/GetTransactionStatus', [
                    'orderTrackingId' => $trackingId,
                ]);

            return $response->successful() ? ($response->json() ?? []) : [];
        } catch (\Throwable $e) {
            Log::error('PesaPal status exception: '.$e->getMessage());
            return [];
        }
    }
}
