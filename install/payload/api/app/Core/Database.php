<?php

/**
 * Database Connection Handler
 * Singleton pattern for database connections
 */

namespace App\Core;

use PDO;
use PDOException;

class Database {
    private static ?PDO $connection = null;
    
    /**
     * Get database connection (singleton)
     */
    public static function getConnection(): PDO {
        if (self::$connection === null) {
            self::$connection = self::createConnection();
        }
        
        return self::$connection;
    }
    
    /**
     * Create new database connection
     */
    private static function createConnection(): PDO {
        // Load config from multiple possible locations
        // Try backend root first, then web root
        $possiblePaths = [
            dirname(__DIR__, 2) . '/config.php',  // From app/Core/ -> app/ -> backend/config.php
            dirname(__DIR__, 3) . '/config.php',  // From app/Core/ -> app/ -> backend/ -> parent
            $_SERVER['DOCUMENT_ROOT'] . '/config.php',  // From web root
            dirname($_SERVER['SCRIPT_FILENAME'], 2) . '/config.php',  // From index.php -> shop/config.php
            dirname($_SERVER['SCRIPT_FILENAME'], 1) . '/config.php',  // Same directory as index.php
        ];
        
        foreach ($possiblePaths as $configPath) {
            if (file_exists($configPath)) {
                require_once $configPath;
                break;
            }
        }
        
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $dbname = defined('DB_NAME') ? DB_NAME : 'crackers1';
        $username = defined('DB_USER') ? DB_USER : 'root';
        $password = defined('DB_PASS') ? DB_PASS : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        
        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection failed");
        }
    }
    
    /**
     * Close database connection
     */
    public static function closeConnection(): void {
        self::$connection = null;
    }
}

