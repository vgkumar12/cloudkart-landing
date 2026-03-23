<?php

namespace App\Core;

use PDO;
use Exception;

class Database {

    private static ?PDO $platformConnection = null;
    private static ?PDO $storeConnection    = null;

    /** Platform DB — users, platform_stores, platform_plans, platform_licences */
    public static function getPlatformConnection(): PDO {
        if (self::$platformConnection === null) {
            self::$platformConnection = self::connect(DB_HOST, PLATFORM_DB_NAME, DB_USER, DB_PASS, DB_CHARSET);
        }
        return self::$platformConnection;
    }

    /** Stores DB — all ck_* tenant tables */
    public static function getStoreConnection(): PDO {
        if (self::$storeConnection === null) {
            self::$storeConnection = self::connect(DB_HOST, STORE_DB_NAME, DB_USER, DB_PASS, DB_CHARSET);
        }
        return self::$storeConnection;
    }

    /** Backward-compat alias → platform connection */
    public static function getConnection(): PDO {
        return self::getPlatformConnection();
    }

    private static function connect(string $host, string $dbname, string $user, string $pass, string $charset): PDO {
        try {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (Exception $e) {
            throw new Exception("Database connection failed ($dbname): " . $e->getMessage());
        }
    }
}
