<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique();          // MSG, SEK
            $table->string('slug')->unique();              // msingi, sekondari
            $table->enum('level', ['primary', 'secondary']);
            $table->string('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('logo')->nullable();            // path to school logo
            $table->json('settings')->nullable();          // per-school config
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('schools');
    }
};
