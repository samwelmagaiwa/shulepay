<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Account activation by SMS.
 *
 * A new account has no usable password. It gets a 6-digit code by SMS; entering the code earns a short-lived
 * token that allows setting the password once. Only a keyed hash of the code is stored, a code expires after
 * CODE_HOURS, and CODE_TRIES wrong guesses burn it so a new one must be requested.
 */
class AccountActivationService
{
    public const CODE_HOURS = 48;

    public const CODE_TRIES = 5;

    public const RESEND_AFTER_SECONDS = 60;

    public const TOKEN_MINUTES = 15;

    public function __construct(private readonly SmsService $sms) {}

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /** The last nine digits identify a Tanzanian number whichever way it was typed (0712…, 255712…, +255712…). */
    public static function phoneKey(string $phone): string
    {
        return substr(preg_replace('/\D+/', '', $phone), -9);
    }

    /**
     * Give the account a new code and text it. The account's password becomes unusable until it is activated.
     *
     * @return bool whether an SMS was sent (false: no phone number, or the gateway refused)
     */
    public function issue(User $user): bool
    {
        $phone = trim((string) $user->phone);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'password' => Str::random(40),
            'must_change_password' => true,
            'activation_code_hash' => $phone !== '' ? $this->hash($code) : null,
            'activation_expires_at' => $phone !== '' ? now()->addHours(self::CODE_HOURS) : null,
            'activation_sent_at' => now(),
            'activation_attempts' => 0,
        ])->saveQuietly();

        if ($phone === '') {
            return false;
        }

        return $this->sms->send($phone, SmsTemplates::activationCode($code, self::CODE_HOURS));
    }

    /** @return Collection<int, User> accounts waiting to be activated that match an email address or phone number */
    public function pending(string $identifier): Collection
    {
        $identifier = trim($identifier);
        $q = User::query()->where('must_change_password', true)->where('is_active', true);

        if (str_contains($identifier, '@')) {
            return $q->where('email', mb_strtolower($identifier))->get();
        }

        $key = self::phoneKey($identifier);
        if (strlen($key) < 9) {
            return collect();
        }

        return $q->where('phone', 'like', '%'.$key)->get()
            ->filter(fn (User $u) => self::phoneKey((string) $u->phone) === $key)->values();
    }

    /** Send a fresh code to the account(s) behind an identifier. Silent when nothing matches or one was sent a moment ago. */
    public function resend(string $identifier): void
    {
        foreach ($this->pending($identifier) as $user) {
            if ($user->activation_sent_at && $user->activation_sent_at->gt(now()->subSeconds(self::RESEND_AFTER_SECONDS))) {
                continue;
            }
            $this->issue($user);
        }
    }

    /** @return ?string a one-time token for choosing the password, or null when the code is wrong */
    public function verify(string $identifier, string $code): ?string
    {
        $candidates = $this->pending($identifier)->filter(fn (User $u) => $u->activation_code_hash);
        $matched = null;

        foreach ($candidates as $user) {
            $usable = $user->activation_attempts < self::CODE_TRIES
                && $user->activation_expires_at?->isFuture();

            if ($usable && hash_equals($user->activation_code_hash, $this->hash($code))) {
                $matched = $user;
                break;
            }
            if ($usable) {
                $user->forceFill(['activation_attempts' => $user->activation_attempts + 1])->saveQuietly();
            }
        }

        if (! $matched) {
            return null;
        }

        // the code is single-use: it is spent as soon as it is accepted
        $matched->forceFill(['activation_code_hash' => null, 'activation_expires_at' => null, 'activation_attempts' => 0])->saveQuietly();

        $token = Str::random(64);
        Cache::put('activation:'.hash('sha256', $token), $matched->id, now()->addMinutes(self::TOKEN_MINUTES));

        return $token;
    }

    /** Set the password for the account the token was issued to; the token works once. */
    public function complete(string $token, string $password): ?User
    {
        $id = Cache::pull('activation:'.hash('sha256', $token));
        $user = $id ? User::find($id) : null;

        if (! $user || ! $user->must_change_password || ! $user->is_active) {
            return null;
        }

        $user->forceFill([
            'password' => $password,
            'must_change_password' => false,
            'activation_code_hash' => null,
            'activation_expires_at' => null,
            'activation_attempts' => 0,
        ])->save();
        $user->tokens()->delete();

        AuditLogger::log('account_activated', $user, []);

        return $user;
    }
}
