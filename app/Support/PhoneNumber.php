<?php

namespace App\Support;

/**
 * Normalizes phone numbers to a canonical `+<digits>` form so that the same
 * number written in different local styles matches at login time.
 *
 * Defaults to the Ethiopian country code (+251) for local formats, matching
 * the app's primary market: `0911…` / `911…` / `9…` (9 digits) all become
 * `+2519…`. Numbers already carrying a country code (`+` or `00`) pass
 * through unchanged.
 */
class PhoneNumber
{
    private const DEFAULT_COUNTRY_CODE = '251';

    /**
     * Normalize the given input, or return null when nothing usable remains.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $value = preg_replace('/[\s().\-]/', '', trim($input));

        if ($value === '' || $value === null) {
            return null;
        }

        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }

        if (str_starts_with($value, '+')) {
            $digits = substr($value, 1);

            return self::isDigits($digits) ? '+'.$digits : null;
        }

        if (! self::isDigits($value)) {
            return null;
        }

        if (str_starts_with($value, '0') && strlen($value) === 10) {
            return '+'.self::DEFAULT_COUNTRY_CODE.substr($value, 1);
        }

        if (strlen($value) === 9) {
            return '+'.self::DEFAULT_COUNTRY_CODE.$value;
        }

        return '+'.$value;
    }

    /**
     * Whether the string contains only digits.
     */
    private static function isDigits(string $value): bool
    {
        return $value !== '' && ctype_digit($value);
    }
}
