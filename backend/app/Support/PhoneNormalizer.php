<?php

namespace App\Support;

class PhoneNormalizer
{
    /**
     * Check if the phone number matches valid Indonesian phone format.
     */
    public static function isValid(string $phone): bool
    {
        $clean = preg_replace('/[\s\-]/', '', $phone);
        return (bool) preg_match('/^(\+62|62|0)8[0-9]{8,12}$/', $clean);
    }

    /**
     * Normalize Indonesian phone number to canonical format 628xxxxxxxxxx.
     */
    public static function normalize(string $phone): string
    {
        $clean = preg_replace('/[\s\-]/', '', $phone);

        if (str_starts_with($clean, '+62')) {
            return substr($clean, 1);
        }

        if (str_starts_with($clean, '0')) {
            return '62' . substr($clean, 1);
        }

        return $clean;
    }
}
