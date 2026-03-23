<?php

/**
 * Customer Controller
 * Customer-facing operations (profile, dashboard)
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Core\Database;

class CustomerController extends Controller {
    
    /**
     * Get customer profile
     * GET /api/customer/profile
     */
    public function profile(): void {
        try {
            // Get user from session/auth
            $userId = $this->request->user['id'] ?? null;
            
            if (!$userId) {
                $this->unauthorized('Authentication required');
                return;
            }
            
            // Use Model method
            $customer = Customer::findByUserId($userId);
            
            if (!$customer) {
                $this->notFound('Customer profile not found');
                return;
            }
            
            $this->success($customer->toArray(), 'Profile retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve profile: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update customer profile
     * PUT /api/customer/profile
     */
    public function updateProfile(): void {
        try {
            $userId = $this->request->user['id'] ?? null;
            
            if (!$userId) {
                $this->unauthorized('Authentication required');
                return;
            }
            
            // Use Model method
            $customer = Customer::findByUserId($userId);
            
            if (!$customer) {
                $this->notFound('Customer profile not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Remove fields that shouldn't be updated
            unset($data['id'], $data['user_id'], $data['email']);
            
            $customer->update($data);
            
            $this->success($customer->toArray(), 'Profile updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get customer dashboard data
     * GET /api/customer/dashboard
     */
    public function dashboard(): void {
        try {
            $userId = $this->request->user['id'] ?? null;
            
            if (!$userId) {
                $this->unauthorized('Authentication required');
                return;
            }
            
            // Use Model method
            $customer = Customer::findByUserId($userId);
            
            if (!$customer) {
                $this->notFound('Customer profile not found');
                return;
            }
            
            // Get orders using Model
            $orders = Order::getAll($customer->id);
            
            // Get order stats
            $totalOrders = count($orders);
            $totalSpent = array_sum(array_column($orders, 'total_amount'));
            $pendingOrders = count(array_filter($orders, fn($o) => in_array($o['order_status'] ?? '', ['pending', 'confirmed'])));
            
            $this->success([
                'customer' => $customer->toArray(),
                'orders' => $orders,
                'stats' => [
                    'total_orders' => $totalOrders,
                    'total_spent' => $totalSpent,
                    'pending_orders' => $pendingOrders
                ]
            ], 'Dashboard data retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve dashboard data: ' . $e->getMessage(), 500);
        }
    }
}
