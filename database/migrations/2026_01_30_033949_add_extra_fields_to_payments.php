<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('payments', function (Blueprint $table) {
        if (!Schema::hasColumn('payments', 'organization')) $table->string('organization')->nullable();
        if (!Schema::hasColumn('payments', 'position')) $table->string('position')->nullable();
        if (!Schema::hasColumn('payments', 'street')) $table->string('street')->nullable();
        if (!Schema::hasColumn('payments', 'region')) $table->string('region')->nullable();
        if (!Schema::hasColumn('payments', 'city')) $table->string('city')->nullable();
        if (!Schema::hasColumn('payments', 'postal')) $table->string('postal')->nullable();
        if (!Schema::hasColumn('payments', 'cohort_id')) $table->integer('cohort_id')->nullable();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            //
        });
    }
};
