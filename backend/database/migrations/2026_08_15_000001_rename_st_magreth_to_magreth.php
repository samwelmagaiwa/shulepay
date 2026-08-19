<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Strip the "St. " / "ST. " prefix from the school name as requested by admin.
        // The authoritative value going forward is the DB record managed by superadmin.
        // Handle all variants: "St. Magreth", "ST.MAGRETH", "ST. MAGRETH", etc.
        $schools = DB::table('schools')
            ->where('code', 'SMP')
            ->where(fn($q) => $q->where('name', 'LIKE', 'St.%')->orWhere('name', 'LIKE', 'ST.%'))
            ->get();

        foreach ($schools as $school) {
            $newName = ltrim(preg_replace('/^[Ss][Tt]\.\s*/u', '', $school->name));
            DB::table('schools')->where('id', $school->id)->update([
                'name' => $newName,
                'slug' => 'magreth-primary',
            ]);
        }
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
