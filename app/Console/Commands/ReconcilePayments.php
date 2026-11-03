<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentVerifier;
use Illuminate\Console\Command;

/**
 * Safety net for PesaPal IPNs that never arrived.
 *
 * A learner finishes paying, PesaPal's notification is lost (host asleep,
 * webhook misconfigured, IPN registered against an old URL) and the learner
 * sits on "not enrolled" forever. This command re-reads the gateway for every
 * order that is still open — and for any row that claims to be paid but was
 * never verified — so a real payment always ends up granting access.
 *
 * Access is only granted from the gateway's answer: a payment that PesaPal has
 * not settled stays locked, no matter what the database row says.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile
        {--days=14 : Look at orders created this many days ago}
        {--reference= : Re-verify a single payment reference}';

    protected $description = 'Re-check PesaPal payments and unlock courses the gateway has confirmed';

    public function handle(PaymentVerifier $verifier): int
    {
        $reference = $this->option('reference');

        if ($reference) {
            $payments = Payment::where('reference', $reference)->get();
        } else {
            $days = max(1, (int) $this->option('days'));

            $placeholders = implode(', ', array_fill(0, count(Payment::PAID_STATUSES), '?'));

            $payments = Payment::query()
                ->where('created_at', '>=', now()->subDays($days))
                ->whereNotNull('tracking_id')
                ->where(function ($query) use ($placeholders) {
                    $query->whereNull('verified_at')
                        ->orWhereRaw(
                            'UPPER(payments.status) NOT IN ('.$placeholders.')',
                            Payment::PAID_STATUSES
                        );
                })
                ->orderBy('id')
                ->get();
        }

        if ($payments->isEmpty()) {
            $this->info('No payments need reconciling.');

            return self::SUCCESS;
        }

        $settled = $unreachable = $open = 0;

        foreach ($payments as $payment) {
            $status = $verifier->settle($payment);
            $payment->refresh();

            if ($status === null) {
                $unreachable++;
                $this->line(sprintf('  %s %s — gateway unreachable, status left as %s', '<fg=yellow>?</>', $payment->reference, $payment->status));

                continue;
            }

            if ($payment->isVerifiedPaid()) {
                $settled++;
                $this->line(sprintf('  %s %s — verified, course access granted', '<fg=green>✓</>', $payment->reference));

                continue;
            }

            $open++;
            $this->line(sprintf('  %s %s — %s, access not granted', '<fg=gray>·</>', $payment->reference, $payment->status));
        }

        $this->newLine();
        $this->line(sprintf(
            'Checked %d payment(s): %d verified, %d still open, %d could not reach PesaPal.',
            $payments->count(), $settled, $open, $unreachable
        ));

        return self::SUCCESS;
    }
}
