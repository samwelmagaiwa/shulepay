<?php

namespace App\Support;

/**
 * A whole number of shillings spelled out, for the "amount in words" line of a payment voucher
 * (the words are what a cashier checks against the figures, so they must never disagree).
 * English and Swahili; anything else falls back to English.
 */
class AmountInWords
{
    private const EN_UNITS = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven',
        'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const EN_TENS = [2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty', 6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'];

    private const SW_UNITS = ['', 'moja', 'mbili', 'tatu', 'nne', 'tano', 'sita', 'saba', 'nane', 'tisa'];

    private const SW_TENS = [1 => 'kumi', 2 => 'ishirini', 3 => 'thelathini', 4 => 'arobaini', 5 => 'hamsini', 6 => 'sitini', 7 => 'sabini', 8 => 'themanini', 9 => 'tisini'];

    /** "TZS" amount in words, e.g. "Two million five hundred thousand shillings only". */
    public static function shillings(int $cents, string $locale = 'en'): string
    {
        $n = intdiv(max(0, $cents), 100);

        if ($locale === 'sw') {
            return ucfirst(self::sw($n)).' tu';
        }

        return ucfirst(self::en($n)).' shillings only';
    }

    public static function en(int $n): string
    {
        if ($n === 0) {
            return 'zero';
        }
        $parts = [];
        foreach ([1_000_000_000 => 'billion', 1_000_000 => 'million', 1_000 => 'thousand'] as $size => $word) {
            if ($n >= $size) {
                $parts[] = self::en3(intdiv($n, $size)).' '.$word;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::en3($n);
        }

        return implode(' ', $parts);
    }

    private static function en3(int $n): string
    {
        $out = [];
        if ($n >= 100) {
            $out[] = self::EN_UNITS[intdiv($n, 100)].' hundred';
            $n %= 100;
            if ($n > 0) {
                $out[] = 'and';
            }
        }
        if ($n >= 20) {
            $out[] = self::EN_TENS[intdiv($n, 10)].($n % 10 ? '-'.self::EN_UNITS[$n % 10] : '');
        } elseif ($n > 0) {
            $out[] = self::EN_UNITS[$n];
        }

        return implode(' ', $out);
    }

    public static function sw(int $n): string
    {
        if ($n === 0) {
            return 'sifuri';
        }
        $parts = [];
        foreach ([1_000_000_000 => 'bilioni', 1_000_000 => 'milioni', 1_000 => 'elfu'] as $size => $word) {
            if ($n >= $size) {
                $parts[] = $word.' '.self::sw3(intdiv($n, $size));
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::sw3($n);
        }

        return implode(' na ', $parts);
    }

    private static function sw3(int $n): string
    {
        $out = [];
        if ($n >= 100) {
            $out[] = 'mia '.self::SW_UNITS[intdiv($n, 100)];
            $n %= 100;
        }
        if ($n >= 10) {
            $out[] = self::SW_TENS[intdiv($n, 10)].($n % 10 ? ' na '.self::SW_UNITS[$n % 10] : '');
        } elseif ($n > 0) {
            $out[] = self::SW_UNITS[$n];
        }

        return implode(' na ', $out);
    }
}
