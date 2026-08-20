<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Assign sort_order to existing school_classes rows based on their name.
 *
 * Order: PP 1 < PP 2 < Darasa la 1 … Darasa la N
 * Any unrecognised name falls to the end (sort_order 999).
 */
return new class extends Migration
{
    public function up(): void
    {
        $classes = DB::table('school_classes')->get(['id', 'name']);

        foreach ($classes as $class) {
            DB::table('school_classes')
                ->where('id', $class->id)
                ->update(['sort_order' => $this->sortOrder($class->name)]);
        }
    }

    public function down(): void
    {
        DB::table('school_classes')->update(['sort_order' => 0]);
    }

    private function sortOrder(string $name): int
    {
        $name = trim($name);

        // PP 1, PP1, pp 1 → 1, 2
        if (preg_match('/^pp\s*(\d+)$/i', $name, $m)) {
            return (int) $m[1]; // PP 1→1, PP 2→2
        }

        // Darasa la N (handles "Darasa la 1", "Darasa La Kwanza", numeric only)
        if (preg_match('/^darasa\s+la\s+(\d+)$/i', $name, $m)) {
            return 2 + (int) $m[1]; // Darasa la 1→3, la 2→4, …
        }

        // Swahili ordinal words
        $ordinals = [
            'kwanza' => 1, 'pili' => 2, 'tatu' => 3, 'nne' => 4,
            'tano' => 5, 'sita' => 6, 'saba' => 7, 'nane' => 8,
            'tisa' => 9, 'kumi' => 10,
        ];
        if (preg_match('/^darasa\s+la\s+(\w+)$/i', $name, $m)) {
            $word = strtolower($m[1]);
            if (isset($ordinals[$word])) {
                return 2 + $ordinals[$word];
            }
        }

        return 999;
    }
};
