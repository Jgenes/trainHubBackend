<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('enrollments', function(Blueprint $table){
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
            $table->enum('status',['PENDING','PAID','CANCELLED'])->default('PENDING');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('enrollments');
    }
};
