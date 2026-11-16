<?php

namespace App\Services;

/** Taka formatting with Bangladeshi digit grouping: 1,20,000.00 (and Bangla digits on request). */
class Money
{
    private const BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    public static function format(float|int|string|null $amount, string $lang = 'en', bool $symbol = false): string
    {
        $value = (float) ($amount ?? 0);
        $negative = $value < 0;
        [$whole, $frac] = explode('.', number_format(abs($value), 2, '.', ''));

        // Last 3 digits, then groups of 2 (lakh / crore system)
        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $grouped = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3 : $last3;

        $out = ($negative ? '-' : '').$grouped.'.'.$frac;
        if ($lang === 'bn') {
            $out = self::bnDigits($out);
        }

        return $symbol ? '৳ '.$out : $out;
    }

    public static function bnDigits(string|int|float $text): string
    {
        return strtr((string) $text, array_combine(range(0, 9), self::BN_DIGITS));
    }

    /** Digits for a document in the given language. */
    public static function digits(string|int|float $text, string $lang): string
    {
        return $lang === 'bn' ? self::bnDigits($text) : (string) $text;
    }
}
