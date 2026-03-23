<?php

/**
 * Simple Autoloader
 * PSR-4 compatible autoloader for the application
 */

namespace App\Core;

class Autoloader {
    private static array $paths = [];
    
    /**
     * Register autoloader
     */
    public static function register(): void {
        spl_autoload_register([self::class, 'load']);
        
        // Add base path
        self::addPath(__DIR__ . '/..');
    }
    
    /**
     * Add path to search for classes
     */
    public static function addPath(string $path): void {
        self::$paths[] = rtrim($path, '/\\');
    }
    
    /**
     * Load class
     */
    public static function load(string $className): void {
        // Remove leading backslash
        $className = ltrim($className, '\\');
        
        // Check if class is in App namespace
        if (strpos($className, 'App\\') !== 0) {
            return; // Not our namespace
        }
        
        // Remove 'App\' prefix
        $relativePath = substr($className, 4);
        
        // Convert namespace to directory path
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $relativePath);
        
        // Search in all registered paths
        foreach (self::$paths as $basePath) {
            $filePath = $basePath . DIRECTORY_SEPARATOR . $path . '.php';
            
            if (file_exists($filePath)) {
                require_once $filePath;
                return;
            }
        }
    }
}

