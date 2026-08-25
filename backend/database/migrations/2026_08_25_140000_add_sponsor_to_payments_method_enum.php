<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 'full_paid' sponsorship records the sponsor's covered amount as a Payment
     * against the invoice (StudentRegistrationService::generateInvoice), so the
     * method enum needs a value for it distinct from cash/mpesa/bank.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY method ".
            "ENUM('cash','mpesa','bank','sponsor') NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        DB::table('payments')->where('method', 'sponsor')->update(['method' => 'cash']);

        DB::statement("ALTER TABLE payments MODIFY method ".
            "ENUM('cash','mpesa','bank') NOT NULL DEFAULT 'cash'");
    }
};
