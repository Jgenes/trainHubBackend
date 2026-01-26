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
       Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
    $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
    $table->string('merchant_reference')->unique();
    $table->string('order_tracking_id')->nullable();
    $table->string('redirect_url')->nullable();
    $table->integer('amount'); // in TZS
    $table->string('currency')->default('TZS');
    $table->enum('status', ['PENDING','COMPLETED','FAILED'])->default('PENDING');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
