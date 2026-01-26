<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role'); // user / tenant / admin
            $table->boolean('is_verified')->default(false);
            $table->string('activation_token')->nullable();
            $table->timestamp('activation_expires_at')->nullable();
            $table->string('login_otp')->nullable();
            $table->timestamp('login_otp_expires_at')->nullable();
            $table->uuid('provider_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
