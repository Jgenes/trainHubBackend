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
        Schema::create('providers', function (Blueprint $table) {
            $table->uuid('id')->primary();

$table->string('legal_name');
$table->string('brand_name')->nullable();
$table->string('provider_type');

$table->string('country')->default('Tanzania');
$table->string('region');
$table->string('district')->nullable();
$table->string('physical_address')->nullable();
$table->string('google_maps_link')->nullable();

$table->string('contact_name');
$table->string('contact_role')->nullable();
$table->string('contact_phone');
$table->string('contact_email');
$table->string('registration_ref')->nullable();
$table->enum('status',['DRAFT','PENDING','APPROVED','REJECTED','SUSPENDED'])
      ->default('PENDING');

$table->string('provider_slug')->unique();
$table->foreignId('created_by');

$table->timestamps();
$table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
