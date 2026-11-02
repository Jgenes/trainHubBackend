<?php

use App\Models\Course;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

/**
 * Older deployments stored course statuses with different casing and labels
 * (PUBLISHED, approved, pending, rejected, …). The public catalogue now uses a
 * case-insensitive deny-list, but canonicalising those rows keeps the admin
 * screens, reports and future writes consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'status')) {
            return;
        }

        // Do the data repair in PHP rather than with LOWER()/TRIM() SQL so it
        // behaves the same on SQLite, MySQL/MariaDB and PostgreSQL.
        DB::table('courses')
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows) {
                foreach ($rows as $row) {
                    $current = is_string($row->status) ? $row->status : null;
                    $canonical = Course::normalizeStatus($current);

                    if ($current !== $canonical) {
                        DB::table('courses')
                            ->where('id', $row->id)
                            ->update(['status' => $canonical]);
                    }
                }
            });

        // The original migration used an enum, which rejects legacy labels and
        // makes future status reconciliation harder. A plain string accepts
        // them; writes still go through Course::normalizeStatus().
        try {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('status', 50)->nullable()->default('Draft')->change();
            });
        } catch (\Throwable $e) {
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `courses` MODIFY `status` VARCHAR(50) NULL DEFAULT 'Draft'");
            }
            // On other drivers, leaving the original column intact is safe:
            // all rows now contain canonical values and the scope is robust.
        }
    }

    public function down(): void
    {
        // Data canonicalisation cannot be reversed reliably.
    }
};
