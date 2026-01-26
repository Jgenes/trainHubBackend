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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id'); // course owner
            $table->string('title');
            $table->string('category')->nullable();
            $table->enum('mode', ['Online', 'Physical', 'Hybrid'])->default('Online');
            $table->text('short_description')->nullable();
            $table->text('long_description')->nullable();
            $table->json('learning_outcomes')->nullable();
            $table->json('skills')->nullable();
            $table->json('requirements')->nullable();
            $table->json('contents')->nullable(); // [{title, description, link}]
            $table->enum('status', ['Draft', 'Published', 'Closed'])->default('Draft');
            $table->string('banner')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
