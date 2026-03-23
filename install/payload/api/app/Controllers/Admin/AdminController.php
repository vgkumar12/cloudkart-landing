<?php

/**
 * Admin Controller
 * Base controller for admin operations
 */

namespace App\Controllers\Admin;

use App\Core\Controller;

class AdminController extends Controller {
    
    /**
     * Dashboard stats
     * GET /api/admin/dashboard
     */
    public function dashboard(): void {
        try {
            // Use Model methods
            $orderStats = Order::getDashboardStats();
            $customerStats = Customer::getStats();
            $totalProducts = Product::getActiveCount();
            
            $stats = array_merge($orderStats, [
                'total_customers' => $customerStats['total'],
                'total_products' => $totalProducts
            ]);
            
            // Recent orders using Model
            $recentOrders = Order::getRecentOrders(10);
            
            $this->success([
                'stats' => $stats,
                'recent_orders' => $recentOrders
            ], 'Dashboard data retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve dashboard data: ' . $e->getMessage(), 500);
        }
    }
}

