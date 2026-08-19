<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('website')->nullable()->after('email');
            $table->string('region')->nullable()->after('address');
            $table->string('district')->nullable()->after('region');
            $table->string('ward')->nullable()->after('district');
            $table->string('owner_name')->nullable()->after('ward');
            $table->unsignedSmallInteger('established_year')->nullable()->after('owner_name');
            $table->unsignedInteger('capacity')->nullable()->comment('max student capacity')->after('established_year');
            $table->string('motto')->nullable()->after('capacity');
        });
    }

    public function down(): void {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['website', 'region', 'district', 'ward', 'owner_name', 'established_year', 'capacity', 'motto']);
        });
    }
};
