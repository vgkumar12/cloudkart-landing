<?php

/**
 * Customer Admin Controller
 * Admin operations for customers (uses existing Customer model)
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Customer;
use App\Models\Order;

class CustomerAdminController extends Controller {
    
    /**
     * Get all customers with stats
     * GET /api/admin/customers
     */
    public function index(): void {
        $search = $this->request->get('q', '') ?: $this->request->get('search', '');
        $page = (int)($this->request->get('page') ?? 1);
        $limit = (int)($this->request->get('limit') ?? 20);
        $sortBy = $this->request->get('sort_by') ?: 'id';
        $sortOrder = $this->request->get('sort_order') ?: 'DESC';
        
        // Validate pagination parameters
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 20;
        if ($limit > 100) $limit = 100;
        
        try {
            // Use Model method with pagination, search, and sorting
            $result = Customer::searchWithPagination($search, $page, $limit, $sortBy, $sortOrder);
            
            $this->success($result, 'Customers retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve customers: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single customer with orders
     * GET /api/admin/customers/{id}
     */
    public function show(int $id): void {
        try {
            $customer = Customer::findById($id);
            
            if (!$customer) {
                $this->notFound('Customer not found');
                return;
            }
            
            $orders = Order::getAll($id);
            
            $customerData = $customer->toArray();
            $customerData['orders'] = $orders;
            $customerData['order_count'] = count($orders);
            $customerData['total_spent'] = array_sum(array_column($orders, 'total_amount'));
            
            $this->success($customerData, 'Customer retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve customer: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update customer
     * PUT /api/admin/customers/{id}
     */
    public function update(int $id): void {
        try {
            $customer = Customer::findById($id);
            
            if (!$customer) {
                $this->notFound('Customer not found');
                return;
            }
            
            $data = $this->request->all();
            $customer->update($data);
            
            $this->success($customer->toArray(), 'Customer updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update customer: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Search customers (for POS autocomplete)
     * GET /api/admin/customers/search
     */
    public function search(): void {
        $query = trim($this->request->get('q', ''));
        
        if (empty($query) || strlen($query) < 2) {
            $this->success(['results' => []], 'No results');
            return;
        }
        
        try {
            // Use Model method
            $results = Customer::searchForOrderManagement($query, 10);
            
            $this->success(['results' => $results], 'Customers retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to search customers: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get customer statistics
     * GET /api/admin/customers/stats
     */
    public function stats(): void {
        try {
            // Use Model method
            $stats = Customer::getStats();
            
            $this->success($stats, 'Customer stats retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve customer stats: ' . $e->getMessage(), 500);
        }
    }
}


