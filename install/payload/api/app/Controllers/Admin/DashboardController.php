<?php

/**
 * Dashboard Controller
 * Admin dashboard statistics
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Order;
use App\Models\Customer;
use App\Core\Database;

class DashboardController extends Controller {
    
    /**
     * Get dashboard statistics
     * GET /api/admin/dashboard/stats
     */
    public function stats(): void {
        try {
            // Get stats using Model methods
            $orderStats = Order::getStats();
            $customerStats = Customer::getStats();
            
            $stats = [
                'total_orders' => $orderStats['total_orders'],
                'total_sales' => $orderStats['total_sales'],
                'total_customers' => $customerStats['total'],
                'pending_orders' => $orderStats['pending_orders'],
                'new_customers_this_month' => $customerStats['new_this_month'],
                'repeat_customers' => $customerStats['repeat_customers']
            ];
            
            // Recent orders using Model
            $recentOrders = Order::getRecentOrders(10);
            
            $this->success([
                'stats' => $stats,
                'recent_orders' => $recentOrders
            ], 'Dashboard stats retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve dashboard stats: ' . $e->getMessage(), 500);
        }
    }
}

