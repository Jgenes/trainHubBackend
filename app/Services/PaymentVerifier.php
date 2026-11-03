<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The only place a payment becomes "paid".
 *
 * Everything that used to trust the caller — the legacy checkout "confirm"
 * endpoint and the internal payment webhook — now asks PesaPal what really
 * happened and applies that answer. As a result a course can only be unlocked
 * by a transaction the gateway confirms, never by a client-side claim or an
 * eager write elsewhere in the code base.
 */
class PaymentVerifier
{
    public function __construct(protected PesaPalService $pesapal)
    {
    }

    /**
     * Ask PesaPal for the state of a payment.
     *
     * Returns null when the gateway cannot be reached or answers something we
     * cannot interpret; callers must then leave the stored status untouched
     * instead of guessing.
     *
     * @return array{status: string, raw: array<string, mixed>}|null
     */
    public function readGatewayStatus(Payment $payment): ?array
    {
        $trackingId = trim((string) ($payment->tracking_id ?? ''));

        if ($trackingId === '') {
            Log::warning('Payment cannot be verified: no PesaPal tracking id.', [
                'payment_id' => $payment->id,
                'reference'  => $payment->reference,
            ]);

            return null;
        }

        try {
            $token = $this->pesapal->getAccessToken();

            if (! $token) {
                return null;
            }

            $response = $this->pesapal->getTransactionStatus($trackingId, $token);
        } catch (\Throwable $e) {
            Log::error('PesaPal status lookup failed: '.$e->getMessage(), [
                'payment_id' => $payment->id,
                'reference'  => $payment->reference,
            ]);

            return null;
        }

        if (! is_array($response) || $response === []) {
            return null;
        }

        if (! empty($response['error'])) {
            Log::warning('PesaPal status lookup returned an error.', [
                'payment_id' => $payment->id,
                'response'   => $response,
            ]);

            return null;
        }

        if (! array_key_exists('payment_status_description', $response)
            && ! array_key_exists('status_code', $response)
            && ! array_key_exists('payment_status_code', $response)) {
            return null;
        }

        return [
            'status' => self::mapStatus($response),
            'raw'    => $response,
        ];
    }

    /**
     * Verify a payment with the gateway and store the result.
     *
     * @return string|null the canonical status now stored, or null when the
     *                     gateway could not be reached (nothing was changed).
     */
    public function settle(Payment $payment): ?string
    {
        $gateway = $this->readGatewayStatus($payment);

        if ($gateway === null) {
            return null;
        }

        return $this->applyStatus($payment, $gateway['status'], $gateway['raw']);
    }

    /**
     * Apply an already-verified gateway response.
     *
     * Only call this with a status that came from PesaPal (or from an
     * authorised human confirmation), never with a client-supplied value.
     *
     * @param  array<string, mixed>  $raw
     */
    public function applyStatus(Payment $payment, string $status, array $raw = []): string
    {
        $status = strtoupper(trim($status));
        $settled = in_array($status, Payment::PAID_STATUSES, true);

        return DB::transaction(function () use ($payment, $status, $raw, $settled) {
            /** @var Payment $fresh */
            $fresh = Payment::query()->lockForUpdate()->find($payment->getKey()) ?? $payment;

            // A late or duplicated IPN must never un-pay a completed order.
            if ($status === 'PENDING' && $fresh->isPaid()) {
                return strtoupper((string) $fresh->status);
            }

            $attributes = ['status' => $status];

            if ($settled) {
                $attributes['verified_at'] = $fresh->verified_at ?? now();
            }

            $fresh->update($attributes);

            if ($settled) {
                Receipt::firstOrCreate(
                    ['payment_id' => $fresh->id],
                    [
                        'receipt_number'        => 'REC-'.strtoupper(Str::random(10)),
                        'amount'                => $fresh->amount,
                        'transaction_reference' => $fresh->tracking_id,
                    ]
                );

                $fresh->invoice()?->update(['status' => 'PAID']);

                // Same rule the PaymentObserver applies; repeated here so a
                // replayed IPN still heals a missing enrollment row.
                $fresh->ensureEnrollment();
            } elseif (in_array($status, ['FAILED', 'INVALID'], true)) {
                Dispute::firstOrCreate(
                    ['payment_id' => $fresh->id, 'reason' => 'Transaction '.$status],
                    ['status' => 'OPEN']
                );
            }

            Log::info('PesaPal transaction settled', [
                'payment_id' => $fresh->id,
                'reference'  => $fresh->reference,
                'status'     => $status,
                'verified'   => $settled,
            ]);

            if ($raw !== []) {
                // Contains payer details, so keep it below the default level.
                Log::debug('PesaPal transaction payload', [
                    'reference' => $fresh->reference,
                    'response'  => $raw,
                ]);
            }

            return $status;
        });
    }

    /**
     * Normalise PesaPal's status payload.
     *
     * v3 answers with a human `payment_status_description` and a numeric
     * `status_code` (0 invalid, 1 completed, 2 failed, 3 reversed). The
     * description wins when present because PesaPal also reports 0 for orders
     * that simply have not been paid yet ("Pending"), and treating those as
     * failures would open bogus disputes.
     *
     * @param  array<string, mixed>  $response
     */
    public static function mapStatus(array $response): string
    {
        $description = strtolower(trim((string) (
            $response['payment_status_description']
            ?? $response['description']
            ?? ''
        )));

        if ($description !== '') {
            if (str_contains($description, 'incomplete')) {
                return 'PENDING';
            }

            if (str_contains($description, 'complete')
                || str_contains($description, 'success')
                || str_contains($description, 'paid')
                || str_contains($description, 'received')) {
                return 'COMPLETED';
            }

            if (str_contains($description, 'pending')
                || str_contains($description, 'process')
                || str_contains($description, 'progress')
                || str_contains($description, 'initiat')
                || str_contains($description, 'await')) {
                return 'PENDING';
            }

            if (str_contains($description, 'invalid')
                || str_contains($description, 'cancel')
                || str_contains($description, 'denied')
                || str_contains($description, 'abort')) {
                return 'INVALID';
            }

            if (str_contains($description, 'fail')
                || str_contains($description, 'revers')
                || str_contains($description, 'refund')
                || str_contains($description, 'declin')
                || str_contains($description, 'reject')) {
                return 'FAILED';
            }
        }

        return match ((string) ($response['status_code'] ?? $response['payment_status_code'] ?? '')) {
            '1'     => 'COMPLETED',
            '2', '3' => 'FAILED',
            default  => 'PENDING',
        };
    }
}
