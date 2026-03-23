<?php

namespace App\Services;

use PDO;
use Exception;

class StoreControlService {
    private $dbHost = DB_HOST;

    /**
     * Get a connection to a specific store's database
     */
    private function getStoreConnection($dbName) {
        try {
            // Fetch credentials from platform DB
            $platformDb = \App\Core\Database::getConnection();
            $stmt = $platformDb->prepare("SELECT db_user, db_pass FROM platform_stores WHERE db_name = ?");
            $stmt->execute([$dbName]);
            $store = $stmt->fetch();

            if (!$store) {
                throw new Exception("Store database $dbName not registered in platform");
            }

            $dsn = "mysql:host={$this->dbHost};dbname=$dbName;charset=utf8mb4";
            return new PDO($dsn, $store['db_user'], $store['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (Exception $e) {
            throw new Exception("Could not connect to store database '$dbName': " . $e->getMessage());
        }
    }

    /**
     * Fetch settings from a store
     */
    public function getStoreSettings($dbName) {
        $db = $this->getStoreConnection($dbName);
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE group_name = 'general'");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /**
     * Update a specific setting in a store
     */
    public function updateStoreSetting($dbName, $key, $value) {
        $db = $this->getStoreConnection($dbName);
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        return $stmt->execute([$value, $key]);
    }

    /**
     * Update multiple settings at once
     */
    public function updateStoreSettings($dbName, $settings) {
        $db = $this->getStoreConnection($dbName);
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        
        $db->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                $stmt->execute([$value, $key]);
            }
            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
