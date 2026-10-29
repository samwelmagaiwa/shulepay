<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\AccountActivationService;
use Illuminate\Console\Command;

/**
 * Accounts created before activation by SMS still sit on the old shared initial password. This gives each of them a
 * code by SMS instead and makes the old password unusable. It texts real people, so it lists who first and asks.
 */
class SendActivationCodes extends Command
{
    protected $signature = 'accounts:send-activation
                            {--dry-run : List who would get a code, send nothing}
                            {--role= : Only accounts with this role (e.g. parent)}
                            {--email= : Only this account}
                            {--limit= : Send to at most this many accounts}
                            {--force : Do not ask for confirmation}';

    protected $description = 'Text an activation code to accounts that still have to set their own password';

    public function handle(AccountActivationService $activation): int
    {
        $targets = User::query()
            ->where('must_change_password', true)->where('is_active', true)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->when($this->option('role'), fn ($q, $r) => $q->role($r))
            ->when($this->option('email'), fn ($q, $e) => $q->where('email', $e))
            ->orderBy('id')
            ->when($this->option('limit'), fn ($q, $n) => $q->limit((int) $n))
            ->get();

        if ($targets->isEmpty()) {
            $this->info('No accounts are waiting for activation (with a phone number).');

            return self::SUCCESS;
        }

        $this->line("Accounts to text: {$targets->count()}");
        foreach ($targets->take(15) as $u) {
            $this->line(sprintf('  [%d] %-32s %s', $u->id, mb_substr($u->name, 0, 32), $u->phone));
        }
        if ($targets->count() > 15) {
            $this->line('  ... and '.($targets->count() - 15).' more.');
        }

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] Nothing sent.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && $this->input->isInteractive()
            && ! $this->confirm("Send {$targets->count()} SMS and disable their current password?", false)) {
            $this->info('Aborted. Nothing changed.');

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($targets as $u) {
            $sent += $activation->issue($u) ? 1 : 0;
        }

        $this->info("Done. {$sent} of {$targets->count()} SMS sent.");

        return $sent === $targets->count() ? self::SUCCESS : self::FAILURE;
    }
}
