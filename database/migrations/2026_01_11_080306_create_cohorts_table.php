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
        Schema::create('cohorts', function (Blueprint $table) {
    $table->id();

    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
    $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();

    $table->string('intake_name');
    $table->date('start_date');
    $table->date('end_date');

    $table->string('schedule_text')->nullable();

    $table->string('venue')->nullable();
    $table->string('online_link')->nullable(); // 🔐 hidden publicly

    $table->integer('capacity');
    $table->integer('seats_taken')->default(0);

    $table->integer('price'); // TZS
    $table->date('registration_deadline');

    $table->enum('status', ['OPEN', 'FULL', 'CLOSED'])->default('OPEN');

    $table->timestamps();

    $table->index(['status', 'start_date']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cohorts');
    }
};
