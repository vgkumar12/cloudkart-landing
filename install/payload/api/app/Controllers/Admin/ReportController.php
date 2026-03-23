<?php

/**
 * Report Controller
 * Admin reports and analytics (uses existing models)
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use App\Models\ComboPack;

class ReportController extends Controller {
    
    /**
     * Get sales report
     * GET /api/admin/reports/sales
     */
    public function sales(): void {
        $startDate = $this->request->get('start_date');
        $endDate = $this->request->get('end_date');
        
        try {
            // Use Model method
            $results = Order::getSalesReport($startDate, $endDate);
            
            // Calculate totals
            $totalOrders = array_sum(array_column($results, 'order_count'));
            $totalRevenue = array_sum(array_column($results, 'revenue'));
            
            $this->success([
                'daily_sales' => $results,
                'summary' => [
                    'total_orders' => (int)$totalOrders,
                    'total_revenue' => (float)$totalRevenue,
                    'avg_order_value' => $totalOrders > 0 ? (float)($totalRevenue / $totalOrders) : 0
                ]
            ], 'Sales report retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve sales report: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get product performance report
     * GET /api/admin/reports/products
     */
    public function products(): void {
        try {
            // Use Model method
            $results = Product::getPerformanceReport(50);
            
            $this->success($results, 'Product performance report retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve product report: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get customer report
     * GET /api/admin/reports/customers
     */
    public function customers(): void {
        try {
            // Use Model method
            $results = Customer::getCustomerReport(50);
            
            $this->success($results, 'Customer report retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve customer report: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get sales by pack type
     * GET /api/admin/reports/sales-by-pack
     */
    public function salesByPack(): void {
        try {
            // Use Model method
            $results = ComboPack::getSalesByPackReport(5);
            
            $this->success($results, 'Sales by pack retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve sales by pack: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get recent activity
     * GET /api/admin/reports/recent-activity
     */
    public function recentActivity(): void {
        $limit = (int)($this->request->get('limit') ?? 10);
        
        try {
            // Use Model method
            $results = \App\Models\OrderLog::getRecentActivity($limit);
            
            $this->success($results, 'Recent activity retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve recent activity: ' . $e->getMessage(), 500);
        }
    }
}


