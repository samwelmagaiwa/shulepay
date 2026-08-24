<?php

namespace App\Enums;

enum SchoolLevel: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Msingi = 'msingi';
    case Sekondari = 'sekondari';

    public static function tryFromValue(?string $value): ?self
    {
        if (! $value) {
            return null;
        }
        $val = strtolower($value);
        if ($val === 'msingi') {
            return self::Primary;
        }
        if ($val === 'sekondari') {
            return self::Secondary;
        }

        return self::tryFrom($val);
    }

    public function label(): string
    {
        return match ($this) {
            self::Primary, self::Msingi => 'Msingi',
            self::Secondary, self::Sekondari => 'Sekondari',
        };
    }

    public function classPrefix(): string
    {
        return match ($this) {
            self::Primary, self::Msingi => 'Darasa',
            self::Secondary, self::Sekondari => 'Kidato',
        };
    }

    /**
     * Prefix used in admission numbers, e.g. SEC/MGRTH/0001/2026.
     *
     * Kept here rather than as a ternary at the call site so the Swahili cases
     * cannot silently fall through to the primary prefix.
     */
    public function admissionPrefix(): string
    {
        return match ($this) {
            self::Primary, self::Msingi => 'PRM',
            self::Secondary, self::Sekondari => 'SEC',
        };
    }
}
