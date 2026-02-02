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
        
        // Relational IDs
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('course_id')->constrained()->onDelete('cascade');
        
        // PesaPal Specific Tracking
        $table->string('reference')->unique(); // ORD-XXXX (Order ID yetu)
        $table->string('tracking_id')->nullable()->unique(); // OrderTrackingId ya PesaPal
        
        // User Details at time of payment (kwa ajili ya kumbukumbu)
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email');
        $table->string('phone_number');
        
        // Transaction Details
        $table->decimal('amount', 15, 2);
        $table->string('currency', 3)->default('TZS');
        $table->text('description')->nullable();
        
        // Status: PENDING, COMPLETED, FAILED, INVALID
        $table->string('status')->default('PENDING');
        
        // Timestamps
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
