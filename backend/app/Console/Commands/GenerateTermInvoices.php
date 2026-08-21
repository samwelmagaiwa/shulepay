<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateTermInvoices extends Command
{
    protected $signature = 'invoices:generate-term';

    protected $description = 'Generate term invoices for all active students at the start of a term';

    public function handle(): void
    {
        //
    }
}
