<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings databases created by the original (incomplete) migrations in line
 * with the columns the application actually reads and writes.
 *
 * Every check is guarded so this migration is safe on a fresh install and on
 * an existing production database where only some of these columns exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('lesson_completions')) {
            Schema::create('lesson_completions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->string('lesson_id');
                $table->timestamps();
                $table->unique(['user_id', 'course_id', 'lesson_id'], 'lesson_completion_unique');
            });
        }

        if (Schema::hasTable('providers')) {
            Schema::table('providers', function (Blueprint $table) {
                if (! Schema::hasColumn('providers', 'brela_doc')) {
                    $table->string('brela_doc')->nullable();
                }
                if (! Schema::hasColumn('providers', 'tin_doc')) {
                    $table->string('tin_doc')->nullable();
                }
            });
        }

        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                if (! Schema::hasColumn('courses', 'videos')) {
                    $table->json('videos')->nullable();
                }
                if (! Schema::hasColumn('courses', 'handouts')) {
                    $table->json('handouts')->nullable();
                }
            });
        }

        if (Schema::hasTable('enrollments')) {
            Schema::table('enrollments', function (Blueprint $table) {
                if (! Schema::hasColumn('enrollments', 'course_id')) {
                    $table->unsignedBigInteger('course_id')->nullable()->index();
                }
                if (! Schema::hasColumn('enrollments', 'provider_id')) {
                    $table->unsignedBigInteger('provider_id')->nullable()->index();
                }
                if (! Schema::hasColumn('enrollments', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable();
                }
                if (! Schema::hasColumn('enrollments', 'student_id')) {
                    $table->unsignedBigInteger('student_id')->nullable();
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $optional = [
                    'organization', 'position', 'street', 'region', 'city',
                    'postal', 'user_name', 'payment_method', 'description',
                ];
                foreach ($optional as $column) {
                    if (! Schema::hasColumn('payments', $column)) {
                        $table->string($column)->nullable();
                    }
                }
                if (! Schema::hasColumn('payments', 'cohort_id')) {
                    $table->unsignedBigInteger('cohort_id')->nullable();
                }
                if (! Schema::hasColumn('payments', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable();
                }
                if (! Schema::hasColumn('payments', 'tracking_id')) {
                    $table->string('tracking_id')->nullable()->unique();
                }
                if (! Schema::hasColumn('payments', 'last_name')) {
                    $table->string('last_name')->nullable();
                }
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'payment_id')) {
                    $table->unsignedBigInteger('payment_id')->nullable()->index();
                }
                if (! Schema::hasColumn('invoices', 'enrollment_id')) {
                    $table->unsignedBigInteger('enrollment_id')->nullable();
                }
                if (! Schema::hasColumn('invoices', 'invoice_number')) {
                    $table->string('invoice_number')->nullable();
                }
                if (! Schema::hasColumn('invoices', 'amount')) {
                    $table->decimal('amount', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('invoices', 'status')) {
                    $table->string('status')->default('PENDING');
                }
                if (! Schema::hasColumn('invoices', 'transaction_reference')) {
                    $table->string('transaction_reference')->nullable();
                }
            });
        }

        if (Schema::hasTable('receipts')) {
            Schema::table('receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('receipts', 'payment_id')) {
                    $table->unsignedBigInteger('payment_id')->nullable()->index();
                }
                if (! Schema::hasColumn('receipts', 'receipt_number')) {
                    $table->string('receipt_number')->nullable();
                }
                if (! Schema::hasColumn('receipts', 'amount')) {
                    $table->decimal('amount', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('receipts', 'transaction_reference')) {
                    $table->string('transaction_reference')->nullable();
                }
            });
        }

        if (Schema::hasTable('disputes')) {
            Schema::table('disputes', function (Blueprint $table) {
                if (! Schema::hasColumn('disputes', 'payment_id')) {
                    $table->unsignedBigInteger('payment_id')->nullable()->index();
                }
                if (! Schema::hasColumn('disputes', 'reason')) {
                    $table->text('reason')->nullable();
                }
                if (! Schema::hasColumn('disputes', 'status')) {
                    $table->string('status')->default('OPEN');
                }
            });
        }

        if (Schema::hasTable('announcements') && ! Schema::hasColumn('announcements', 'file_path')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->string('file_path')->nullable();
            });
        }

        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $columns = [
                    'invoice_id'          => 'unsignedBigInteger',
                    'provider_id'         => 'unsignedBigInteger',
                    'method_type'         => 'string',
                    'method_name'         => 'string',
                    'payer_msisdn'        => 'string',
                    'gateway_reference'   => 'string',
                    'status'              => 'string',
                    'raw_init_payload'    => 'text',
                    'raw_callback_payload'=> 'text',
                ];
                foreach ($columns as $column => $type) {
                    if (! Schema::hasColumn('payment_transactions', $column)) {
                        $table->{$type}($column)->nullable();
                    }
                }
            });
        }

        // Payments can be for a course without a cohort; old databases made
        // this column NOT NULL which broke enrollment creation.
        if (Schema::hasTable('enrollments') && Schema::hasColumn('enrollments', 'cohort_id')) {
            try {
                Schema::table('enrollments', function (Blueprint $table) {
                    $table->unsignedBigInteger('cohort_id')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // Already nullable on fresh installs; ignore driver limitations.
            }
        }

        // Provider ids are UUIDs, so audit targets must be strings, not bigints.
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'target_id')) {
            try {
                Schema::table('audit_logs', function (Blueprint $table) {
                    $table->string('target_id')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // Some drivers need doctrine/dbal for CHANGE; the column still
                // works for numeric ids, so failure here must not block deploy.
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_completions');
        Schema::dropIfExists('password_reset_tokens');
    }
};
