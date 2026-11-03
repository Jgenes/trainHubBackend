<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records the moment a payment was confirmed as settled.
 *
 * Course access is never derived from a client-side "I paid" claim, only from a
 * payment PesaPal (or an authorised manual confirmation) has verified.
 * `verified_at` is that proof: TrainingController reports `is_enrolled: true`
 * only when a paid status *and* this timestamp are both present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'verified_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->timestamp('verified_at')->nullable()->index();
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'verified_at')) {
            $this->backfillExistingPayments();
        }
    }

    /**
     * Keep access for learners who really paid, without keeping it for rows that
     * were flagged paid without the gateway ever confirming them.
     *
     *  - a receipt exists  -> the PesaPal callback settled this payment, or
     *  - no tracking id    -> an offline/manual payment the gateway never knew
     *                         about (confirmed by a provider in the dashboard).
     *
     * Everything else — a paid status on a row that has a PesaPal tracking id
     * but no receipt — is treated as unverified. `php artisan
     * payments:reconcile` re-reads the gateway for those and restores access if
     * the transaction was genuinely completed.
     */
    private function backfillExistingPayments(): void
    {
        $hasReceipts = Schema::hasTable('receipts') && Schema::hasColumn('receipts', 'payment_id');

        $query = DB::table('payments')
            ->whereRaw("UPPER(status) IN ('COMPLETED', 'PAID', 'SUCCESS')")
            ->whereNull('verified_at');

        if ($hasReceipts) {
            $query->where(function ($query) {
                $query->whereNull('payments.tracking_id')
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('receipts')
                            ->whereColumn('receipts.payment_id', 'payments.id');
                    });
            });
        }

        $query->update([
            'verified_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'verified_at')) {
            try {
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropColumn('verified_at');
                });
            } catch (\Throwable $e) {
                // Drivers that cannot drop an indexed column can keep it.
            }
        }
    }
};
