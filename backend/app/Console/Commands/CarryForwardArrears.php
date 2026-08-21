<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CarryForwardArrears extends Command
{
    protected $signature = 'arrears:carry-forward';

    protected $description = 'Carry forward unpaid balances from the previous term as arrears';

    public function handle(): void
    {
        //
    }
}
