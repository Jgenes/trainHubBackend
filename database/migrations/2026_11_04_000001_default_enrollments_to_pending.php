<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Do not relabel historical enrollments: their payment must be audited
        // separately. Only future inserts without an explicit status change.
        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'PAID', 'CANCELLED'])->default('PENDING')->change();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'PAID', 'CANCELLED'])->default('PAID')->change();
        });
    }
};
