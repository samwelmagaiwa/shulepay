<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendFeeReminders extends Command
{
    protected $signature = 'fees:send-reminders';

    protected $description = 'Tuma SMS za vikumbusho vya deni';

    public function handle(): void
    {
        //
    }
}
