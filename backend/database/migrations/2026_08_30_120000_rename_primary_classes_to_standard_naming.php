<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename primary classes from "Darasa la N" / "PP N" to the worded form.
 *
 * Secondary classes were already stored as FORM ONE..FORM FOUR, so primary was
 * the odd one out; this brings both levels onto one convention.
 *
 * Renaming is safe for existing records because everything that references a
 * class does so by school_class_id: enrollments, fee structures and reports all
 * join on the id, and the aggregates that group by name derive their labels
 * from the row rather than from a hardcoded list. The places that DID read the
 * name as a string were updated in the same release, and each accepts both the
 * old and the new spelling so this migration can run before or after the code
 * reaches a given environment.
 *
 * Matching is on the exact old name so the migration is idempotent: run twice,
 * the second pass matches nothing.
 */
return new class extends Migration
{
    /** Old name => new name. */
    private const RENAMES = [
        'PP 1' => 'PP ONE',
        'PP 2' => 'PP TWO',
        'Darasa la 1' => 'STANDARD ONE',
        'Darasa la 2' => 'STANDARD TWO',
        'Darasa la 3' => 'STANDARD THREE',
        'Darasa la 4' => 'STANDARD FOUR',
        'Darasa la 5' => 'STANDARD FIVE',
        'Darasa la 6' => 'STANDARD SIX',
        'Darasa la 7' => 'STANDARD SEVEN',
    ];

    public function up(): void
    {
        // sort_order is left untouched: it is already stored per row, so the
        // existing PP-then-Standard ordering survives the rename.
        foreach (self::RENAMES as $old => $new) {
            DB::table('school_classes')
                ->where('name', $old)
                ->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('school_classes')
                ->where('name', $new)
                ->update(['name' => $old]);
        }
    }
};
