<?php

/**
 * Logger Helper
 * Handles application logging
 */

namespace App\Helpers;

class Logger {
    private static ?string $logPath = null;
    
    /**
     * Initialize logger
     */
    public static function init(): void {
        // Use existing log path from config if available
        if (defined('LOG_PATH')) {
            self::$logPath = LOG_PATH;
        } else {
            self::$logPath = dirname(__DIR__, 3) . '/logs/';
        }
        
        // Create logs directory if it doesn't exist
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
    }
    
    /**
     * Log message
     */
    public static function log(string $level, string $message, array $context = []): void {
        if (self::$logPath === null) {
            self::init();
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        $logFile = self::$logPath . 'app_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Log info message
     */
    public static function info(string $message, array $context = []): void {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning(string $message, array $context = []): void {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log error message
     */
    public static function error(string $message, array $context = []): void {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Log debug message
     */
    public static function debug(string $message, array $context = []): void {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            self::log('DEBUG', $message, $context);
        }
    }
}



