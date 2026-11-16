<?php

namespace App\Services;

/**
 * Taka amounts in words, using the Bangladeshi lakh / crore system.
 *   en(120550.5) → "Taka One Lakh Twenty Thousand Five Hundred Fifty and Fifty Paisa Only"
 *   bn(120550.5) → "এক লক্ষ বিশ হাজার পাঁচশত পঞ্চাশ টাকা পঞ্চাশ পয়সা মাত্র"
 */
class AmountInWords
{
    private const EN_ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const EN_TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /** Bangla numbers 0–99 are irregular words, so they are listed in full. */
    private const BN = ['', 'এক', 'দুই', 'তিন', 'চার', 'পাঁচ', 'ছয়', 'সাত', 'আট', 'নয়',
        'দশ', 'এগারো', 'বারো', 'তেরো', 'চৌদ্দ', 'পনেরো', 'ষোলো', 'সতেরো', 'আঠারো', 'উনিশ',
        'বিশ', 'একুশ', 'বাইশ', 'তেইশ', 'চব্বিশ', 'পঁচিশ', 'ছাব্বিশ', 'সাতাশ', 'আটাশ', 'উনত্রিশ',
        'ত্রিশ', 'একত্রিশ', 'বত্রিশ', 'তেত্রিশ', 'চৌত্রিশ', 'পঁয়ত্রিশ', 'ছত্রিশ', 'সাঁইত্রিশ', 'আটত্রিশ', 'উনচল্লিশ',
        'চল্লিশ', 'একচল্লিশ', 'বিয়াল্লিশ', 'তেতাল্লিশ', 'চুয়াল্লিশ', 'পঁয়তাল্লিশ', 'ছেচল্লিশ', 'সাতচল্লিশ', 'আটচল্লিশ', 'উনপঞ্চাশ',
        'পঞ্চাশ', 'একান্ন', 'বায়ান্ন', 'তিপ্পান্ন', 'চুয়ান্ন', 'পঞ্চান্ন', 'ছাপ্পান্ন', 'সাতান্ন', 'আটান্ন', 'উনষাট',
        'ষাট', 'একষট্টি', 'বাষট্টি', 'তেষট্টি', 'চৌষট্টি', 'পঁয়ষট্টি', 'ছেষট্টি', 'সাতষট্টি', 'আটষট্টি', 'উনসত্তর',
        'সত্তর', 'একাত্তর', 'বাহাত্তর', 'তিয়াত্তর', 'চুয়াত্তর', 'পঁচাত্তর', 'ছিয়াত্তর', 'সাতাত্তর', 'আটাত্তর', 'উনআশি',
        'আশি', 'একাশি', 'বিরাশি', 'তিরাশি', 'চুরাশি', 'পঁচাশি', 'ছিয়াশি', 'সাতাশি', 'আটাশি', 'উননব্বই',
        'নব্বই', 'একানব্বই', 'বিরানব্বই', 'তিরানব্বই', 'চুরানব্বই', 'পঁচানব্বই', 'ছিয়ানব্বই', 'সাতানব্বই', 'আটানব্বই', 'নিরানব্বই'];

    public static function en(float|int|string $amount): string
    {
        [$taka, $paisa] = self::split($amount);
        $words = $taka > 0 ? self::enWhole($taka) : 'Zero';
        $out = 'Taka '.$words;
        if ($paisa > 0) {
            $out .= ' and '.self::enWhole($paisa).' Paisa';
        }

        return $out.' Only';
    }

    public static function bn(float|int|string $amount): string
    {
        [$taka, $paisa] = self::split($amount);
        $out = ($taka > 0 ? self::bnWhole($taka) : 'শূন্য').' টাকা';
        if ($paisa > 0) {
            $out .= ' '.self::bnWhole($paisa).' পয়সা';
        }

        return $out.' মাত্র';
    }

    public static function for(string $lang, float|int|string $amount): string
    {
        return $lang === 'bn' ? self::bn($amount) : self::en($amount);
    }

    /** [whole taka, paisa] rounded to 2 decimals, using string math to avoid float errors. */
    private static function split(float|int|string $amount): array
    {
        $s = number_format(abs((float) $amount), 2, '.', '');
        [$whole, $frac] = explode('.', $s);

        return [(int) $whole, (int) $frac];
    }

    /** Split into crore / lakh / thousand / hundred / rest (crore can repeat for huge numbers). */
    private static function parts(int $n): array
    {
        return [
            'crore' => intdiv($n, 10000000),
            'lakh' => intdiv($n % 10000000, 100000),
            'thousand' => intdiv($n % 100000, 1000),
            'hundred' => intdiv($n % 1000, 100),
            'rest' => $n % 100,
        ];
    }

    private static function enTwo(int $n): string
    {
        if ($n < 20) {
            return self::EN_ONES[$n];
        }

        return trim(self::EN_TENS[intdiv($n, 10)].' '.self::EN_ONES[$n % 10]);
    }

    private static function enWhole(int $n): string
    {
        $p = self::parts($n);
        $w = [];
        if ($p['crore']) {
            $w[] = self::enWhole($p['crore']).' Crore';
        }
        if ($p['lakh']) {
            $w[] = self::enTwo($p['lakh']).' Lakh';
        }
        if ($p['thousand']) {
            $w[] = self::enTwo($p['thousand']).' Thousand';
        }
        if ($p['hundred']) {
            $w[] = self::EN_ONES[$p['hundred']].' Hundred';
        }
        if ($p['rest']) {
            $w[] = self::enTwo($p['rest']);
        }

        return implode(' ', $w);
    }

    private static function bnWhole(int $n): string
    {
        $p = self::parts($n);
        $w = [];
        if ($p['crore']) {
            $w[] = self::bnWhole($p['crore']).' কোটি';
        }
        if ($p['lakh']) {
            $w[] = self::BN[$p['lakh']].' লক্ষ';
        }
        if ($p['thousand']) {
            $w[] = self::BN[$p['thousand']].' হাজার';
        }
        if ($p['hundred']) {
            $w[] = self::BN[$p['hundred']].'শত';
        }
        if ($p['rest']) {
            $w[] = self::BN[$p['rest']];
        }

        return implode(' ', $w);
    }
}
