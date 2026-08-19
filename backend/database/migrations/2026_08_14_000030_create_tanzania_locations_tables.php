<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country_code', 5)->default('TZ');
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('states')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lga_id')->constrained('districts')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained('wards')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained('villages')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'region')) {
                $table->string('region')->nullable()->after('photo');
            }
            if (!Schema::hasColumn('students', 'district')) {
                $table->string('district')->nullable()->after('region');
            }
            if (!Schema::hasColumn('students', 'ward')) {
                $table->string('ward')->nullable()->after('district');
            }
            if (!Schema::hasColumn('students', 'street')) {
                $table->string('street')->nullable()->after('ward');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['region', 'district', 'ward', 'street']);
        });

        Schema::dropIfExists('places');
        Schema::dropIfExists('villages');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('states');
    }
};
