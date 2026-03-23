<?php

/**
 * Generate CSRF Token
 * Creates a token if one doesn't exist, or returns the existing one.
 * 
 * @return string The CSRF token
 */
function generateCsrfToken()
{
    // Session is already started in config.php
    // No need to start it again here

    if (empty($_SESSION['csrf_token'])) {
        if (function_exists('random_bytes')) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 * 
 * @param string $token The token submitted in the form
 * @return bool True if valid, False otherwise
 */
function verifyCsrfToken($token)
{
    // Session is already started in config.php
    // No need to start it again here

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
