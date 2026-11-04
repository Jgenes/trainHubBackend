<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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

        $callbackUrl = route('pesapal.callback');
        $cacheKey = 'pesapal.ipn.'.sha1($this->baseUrl.'|'.$callbackUrl);
        $cachedId = Cache::get($cacheKey);
        if (is_string($cachedId) && $cachedId !== '') {
            return $cachedId;
        }

        try {
            $response = Http::withToken($token)->asJson()->timeout(20)
                ->post($this->baseUrl.'/api/URLSetup/RegisterIPN', [
                    'url'                   => $callbackUrl,
                    'ipn_notification_type' => 'POST',
                ]);

            $ipnId = $response->json('ipn_id');
            if ($response->failed() || ! is_string($ipnId) || $ipnId === '') {
                Log::error('PesaPal IPN registration failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            Cache::put($cacheKey, $ipnId, now()->addDays(30));
            return $ipnId;
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
