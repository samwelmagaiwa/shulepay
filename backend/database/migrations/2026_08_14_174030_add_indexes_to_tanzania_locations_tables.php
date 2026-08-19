<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('districts', function (Blueprint $table) {
            $table->index('state_id');
            $table->index('name');
        });

        Schema::table('wards', function (Blueprint $table) {
            $table->index('lga_id');
            $table->index('name');
        });

        Schema::table('villages', function (Blueprint $table) {
            $table->index('ward_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('villages', function (Blueprint $table) {
            $table->dropIndex(['ward_id']);
            $table->dropIndex(['name']);
        });

        Schema::table('wards', function (Blueprint $table) {
            $table->dropIndex(['lga_id']);
            $table->dropIndex(['name']);
        });

        Schema::table('districts', function (Blueprint $table) {
            $table->dropIndex(['state_id']);
            $table->dropIndex(['name']);
        });

        Schema::table('states', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
