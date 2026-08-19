<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Strip the "St. " / "ST. " prefix from the school name as requested by admin.
        // The authoritative value going forward is the DB record managed by superadmin.
        // Handle all variants: "St. Magreth", "ST.MAGRETH", "ST. MAGRETH", etc.
        DB::table('schools')
            ->where('code', 'SMP')
            ->where('name', 'LIKE', 'St.%')
            ->orWhere(fn($q) => $q->where('code', 'SMP')->where('name', 'LIKE', 'ST.%'))
            ->update([
                'name' => DB::raw("REGEXP_REPLACE(name, '^[Ss][Tt]\\.\\\\s*', '')"),
                'slug' => 'magreth-primary',
            ]);
    }

    public function down(): void
    {
        DB::table('schools')
            ->where('code', 'SMP')
            ->where('slug', 'magreth-primary')
            ->update([
                'name' => 'St. Magreth Primary School',
                'slug' => 'st-magreth-primary',
            ]);
    }
};
