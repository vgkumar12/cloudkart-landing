<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Services\StoreControlService;

class DashboardController {
    public function getDashboardData() {
        $request = new Request();
        $response = new Response();
        $db = Database::getConnection();

        $userId = $request->input('user_id');
        $role = $request->input('role');

        if (!$userId) {
            $response->error("Authentication required");
        }

        try {
            if ($role === 'admin') {
                // Super Admin: Get all stores
                $stmt = $db->query("SELECT s.*, u.name as owner_name, p.name as plan_name 
                                  FROM platform_stores s 
                                  JOIN users u ON s.user_id = u.id 
                                  JOIN platform_plans p ON s.plan_id = p.id
                                  ORDER BY s.created_at DESC");
                $stores = $stmt->fetchAll();
            } else {
                // Owner: Get own stores
                $stmt = $db->prepare("SELECT s.*, p.name as plan_name 
                                    FROM platform_stores s 
                                    JOIN platform_plans p ON s.plan_id = p.id
                                    WHERE s.user_id = ? 
                                    ORDER BY s.created_at DESC");
                $stmt->execute([$userId]);
                $stores = $stmt->fetchAll();
            }

            $response->success(['stores' => $stores], "Dashboard data retrieved");
        } catch (\Exception $e) {
            $response->error("Failed to load dashboard: " . $e->getMessage());
        }
    }

    public function getStoreSettings() {
        $request = new Request();
        $response = new Response();
        $dbName = $request->input('db_name');

        if (!$dbName) {
            $response->error("Database name required");
        }

        try {
            $controlService = new StoreControlService();
            $settings = $controlService->getStoreSettings($dbName);
            $response->success(['settings' => $settings], "Store settings retrieved");
        } catch (\Exception $e) {
            $response->error("Failed to load store settings: " . $e->getMessage());
        }
    }

    public function updateStoreSettings() {
        $request = new Request();
        $response = new Response();
        
        $dbName = $request->input('db_name');
        $settings = $request->input('settings'); // Expects key-value array

        if (!$dbName || !is_array($settings)) {
            $response->error("Invalid request data");
        }

        try {
            $controlService = new StoreControlService();
            $controlService->updateStoreSettings($dbName, $settings);
            $response->success([], "Store settings updated successfully");
        } catch (\Exception $e) {
            $response->error("Failed to update store settings: " . $e->getMessage());
        }
    }
}
