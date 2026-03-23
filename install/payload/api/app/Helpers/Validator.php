<?php

/**
 * Validator Helper
 * Provides validation functions
 */

namespace App\Helpers;

class Validator {
    /**
     * Validate email
     */
    public static function email(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number (Indian format)
     */
    public static function phone(string $phone): bool {
        // Remove spaces and special characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Indian phone numbers: 10 digits, optionally with country code
        return preg_match('/^(\+91)?[6-9]\d{9}$/', $phone) === 1;
    }
    
    /**
     * Validate pincode (Indian format)
     */
    public static function pincode(string $pincode): bool {
        return preg_match('/^[1-9][0-9]{5}$/', $pincode) === 1;
    }
    
    /**
     * Validate required field
     */
    public static function required($value): bool {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        return $value !== null && $value !== '';
    }
    
    /**
     * Validate minimum length
     */
    public static function minLength(string $value, int $min): bool {
        return strlen($value) >= $min;
    }
    
    /**
     * Validate maximum length
     */
    public static function maxLength(string $value, int $max): bool {
        return strlen($value) <= $max;
    }
    
    /**
     * Validate numeric value
     */
    public static function numeric($value): bool {
        return is_numeric($value);
    }
    
    /**
     * Validate integer value
     */
    public static function integer($value): bool {
        return is_int($value) || (is_string($value) && ctype_digit($value));
    }
    
    /**
     * Validate positive number
     */
    public static function positive($value): bool {
        return is_numeric($value) && (float)$value > 0;
    }
    
    /**
     * Validate date format
     */
    public static function date(string $date, string $format = 'Y-m-d'): bool {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

