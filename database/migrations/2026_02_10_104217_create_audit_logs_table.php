<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admin_id')->constrained('users');
    $table->string('action'); // e.g., SUSPEND_PROVIDER
    $table->string('target_type'); // e.g., Provider, Course, Payment
    $table->unsignedBigInteger('target_id');
    $table->text('reason')->nullable();
    $table->json('old_values')->nullable(); // Thamani kabla ya kubadilishwa
    $table->json('new_values')->nullable(); // Thamani baada ya kubadilishwa
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
