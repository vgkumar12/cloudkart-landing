<?php

/**
 * Google Authentication Helper
 */

namespace App\Helpers;

class GoogleAuth {
    
    /**
     * Verify Google ID token
     * 
     * @param string $idToken Google ID token
     * @return array|false User data or false on failure
     */
    public static function verifyToken(string $idToken) {
        try {
            // Get Google Client ID from settings with fallback to config
            $googleClientId = \App\Helpers\SettingsHelper::getGoogleClientId();
            
            if (empty($googleClientId)) {
                // Fallback: try to load from config.php
                $possiblePaths = [
                    dirname(__DIR__, 2) . '/config.php',
                    dirname(__DIR__, 3) . '/config.php',
                    dirname(__DIR__, 4) . '/config.php',
                ];
                
                foreach ($possiblePaths as $path) {
                    if (file_exists($path) && !defined('GOOGLE_CLIENT_ID')) {
                        require_once $path;
                        break;
                    }
                }
                
                if (defined('GOOGLE_CLIENT_ID')) {
                    $googleClientId = GOOGLE_CLIENT_ID;
                } else {
                    return false;
                }
            }
            
            // Verify token with Google
            $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$response) {
                return false;
            }
            
            $data = json_decode($response, true);
            
            if (!$data || json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
            
            // Verify the token is for our app
            if (isset($data['aud']) && $data['aud'] === $googleClientId) {
                return [
                    'google_id' => $data['sub'] ?? '',
                    'email' => $data['email'] ?? '',
                    'name' => $data['name'] ?? '',
                    'picture' => $data['picture'] ?? null,
                    'picture_url' => $data['picture'] ?? null, // Alias for compatibility
                    'email_verified' => $data['email_verified'] ?? false
                ];
            }
            
            return false;
        } catch (\Exception $e) {
            error_log('Google token verification error: ' . $e->getMessage());
            return false;
        }
    }
}

