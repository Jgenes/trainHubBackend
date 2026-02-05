<!-- <?php

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
    Schema::create('lesson_user', function (Blueprint $table) {
        // $table->id();
        // $table->foreignId('user_id')->constrained()->onDelete('cascade');
        // $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
        // $table->timestamps();
        
        // // Zuia kurekodi mara mbili somo moja kwa user mmoja
        // $table->unique(['user_id', 'lesson_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_user');
    }
}; -->
