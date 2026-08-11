<?php

namespace App\Services;

use InvalidArgumentException;

class PhoneNumberService
{
    public function normalize(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', $phone);

        if (blank($phone)) {
            return null;
        }

        // 0650537539 -> 255650537539
        if (preg_match('/^0([67]\d{8})$/', $phone, $matches)) {
            return '255' . $matches[1];
        }

        // 650537539 -> 255650537539
        if (preg_match('/^[67]\d{8}$/', $phone)) {
            return '255' . $phone;
        }

        // Already normalized.
        if (preg_match('/^255[67]\d{8}$/', $phone)) {
            return $phone;
        }

        throw new InvalidArgumentException(
            'Invalid Tanzanian mobile number. Use 0650537539, +255650537539, or 255650537539.'
        );
    }

    public function isValid(?string $phone): bool
    {
        try {
            return filled(
                $this->normalize($phone)
            );
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}