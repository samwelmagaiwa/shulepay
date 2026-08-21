<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendFeeReminders extends Command
{
    protected $signature = 'fees:send-reminders';

    protected $description = 'Send SMS fee reminders to guardians with outstanding balances';

    public function handle(): void
    {
        //
    }
}
