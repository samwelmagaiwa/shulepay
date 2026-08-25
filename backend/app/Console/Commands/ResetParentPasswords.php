<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Guardian accounts used to be created with bcrypt(Str::random(16)) — a password
 * that was never shown, stored or sent. With no password-reset route in the API,
 * those parents can never sign in.
 *
 * This sets them to the same default staff accounts use ('SCHOOL') and flags
 * must_change_password, so the parent is forced to choose their own on first login.
 *
 * Only the password and that flag are written; no other field is touched.
 */
class ResetParentPasswords extends Command
{
    protected $signature = 'parents:reset-passwords
                            {--dry-run : Show which accounts would be reset}
                            {--email= : Restrict to a single account}
                            {--all : Include parents who have already set their own password}';

    protected $description = 'Give locked-out parent accounts the default password and force a change on first login';

    private const DEFAULT_PASSWORD = 'SCHOOL';

    public function handle(): int
    {
        $query = User::role('parent')
            ->when($this->option('email'), fn ($q, $e) => $q->where('email', $e));

        $parents = $query->get();

        // Without --all, skip anyone who already knows their password: either they
        // have already been reset (must_change_password) or they have set their own
        // (the default no longer matches).
        $targets = $this->option('all')
            ? $parents
            : $parents->filter(fn (User $u) => ! $u->must_change_password
                && ! Hash::check(self::DEFAULT_PASSWORD, $u->password));

        if ($targets->isEmpty()) {
            $this->info('No parent accounts need resetting.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("Accounts to reset: {$targets->count()} of {$parents->count()} parent(s)");
        foreach ($targets->take(15) as $u) {
            $this->line(sprintf('  [%d] %-36s %s', $u->id, $u->email, $u->phone ?? '-'));
        }
        if ($targets->count() > 15) {
            $this->line('  ... and '.($targets->count() - 15).' more.');
        }
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] No changes written.');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive()
            && ! $this->confirm("Reset {$targets->count()} parent password(s) to '".self::DEFAULT_PASSWORD."'?", false)) {
            $this->info('Aborted. Nothing changed.');

            return self::SUCCESS;
        }

        $done = 0;
        foreach ($targets as $u) {
            $u->forceFill([
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'must_change_password' => true,
            ])->saveQuietly();
            $done++;
        }

        $this->info("Done. {$done} parent account(s) reset.");
        $this->line("Parents sign in with their email and the password '".self::DEFAULT_PASSWORD."', then must set their own.");

        return self::SUCCESS;
    }
}
