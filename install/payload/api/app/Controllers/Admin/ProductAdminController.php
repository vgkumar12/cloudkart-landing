<?php

/**
 * Product Admin Controller
 * Admin CRUD operations for products (uses existing Product model)
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Helpers\ImageHelper;

class ProductAdminController extends Controller {
    
    /**
     * Get all products (with pagination and filters)
     * GET /api/admin/products
     */
    public function index(): void {
        $search = $this->request->get('q', '') ?: $this->request->get('search', '');
        $categoryId = $this->request->get('category_id') ? (int)$this->request->get('category_id') : null;
        // Support both 'page' and 'p' for backward compatibility
        $page = (int)($this->request->get('page') ?? $this->request->get('p') ?? 1);
        // Support 'limit' parameter from frontend, default to 20
        $limit = (int)($this->request->get('limit') ?? 20);
        // Sorting parameters
        $sortBy = $this->request->get('sort_by') ?: 'id';
        $sortOrder = $this->request->get('sort_order') ?: 'DESC';
        
        // Validate pagination parameters
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 20;
        if ($limit > 100) $limit = 100; // Max limit to prevent abuse
        
        try {
            // Use Model method with sorting
            $result = Product::searchWithPagination($search, $categoryId, $page, $limit, $sortBy, $sortOrder);
            
            $this->success($result, 'Products retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve products: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single product
     * GET /api/admin/products/{id}
     */
    public function show(int $id): void {
        try {
            $product = Product::findById($id);
            
            if (!$product) {
                $this->notFound('Product not found');
                return;
            }
            
            // Return product in expected format
            $this->success(['product' => $product->toArray()], 'Product retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve product: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create product
     * POST /api/admin/products
     */
    public function store(): void {
        try {
            $data = $this->request->all();
            
            // Debug: Log received data (remove in production)
            // error_log('Product store data: ' . print_r($data, true));
            
            // Handle image upload if provided
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 4) . '/uploads/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $data['image_path'] = 'uploads/products/' . $fileName;
                }
            }
            
            // Process boolean fields
            $data['is_active'] = isset($data['is_active']) ? 1 : 0;
            $data['is_featured'] = isset($data['is_featured']) ? 1 : 0;
            $data['is_digital'] = isset($data['is_digital']) ? 1 : 0;
            $data['requires_shipping'] = isset($data['requires_shipping']) ? 1 : 0;
            
            // Process price field first (convert to float)
            if (isset($data['price'])) {
                $data['price'] = (float)$data['price'];
            }
            
            // Process nullable fields
            $data['sale_price'] = !empty($data['sale_price']) ? (float)$data['sale_price'] : null;
            $data['cost_price'] = !empty($data['cost_price']) ? (float)$data['cost_price'] : null;
            $data['wholesale_rate'] = !empty($data['wholesale_rate']) ? (float)$data['wholesale_rate'] : null;
            $data['quantity_per_carton'] = !empty($data['quantity_per_carton']) ? (int)$data['quantity_per_carton'] : null;
            $data['wholesale_rate_per_carton'] = !empty($data['wholesale_rate_per_carton']) ? (float)$data['wholesale_rate_per_carton'] : null;
            
            // Process list_price (always set it, allow 0)
            if (isset($data['list_price']) && $data['list_price'] !== '' && $data['list_price'] !== null) {
                // Convert to float, allow 0
                $data['list_price'] = (float)$data['list_price'];
            } elseif (isset($data['price']) && $data['price'] > 0) {
                // If list_price is not provided but price is, auto-calculate it
                $data['list_price'] = (float)$data['price'] * 5;
            } else {
                // Default to null if neither is set
                $data['list_price'] = null;
            }
            
            // Handle brand_id
            if (isset($data['brand_id'])) {
                $data['brand_id'] = !empty($data['brand_id']) ? (int)$data['brand_id'] : null;
            }
            
            // Extract attribute_value_ids before creating product
            $attributeValueIds = $data['attribute_value_ids'] ?? [];
            // Decode JSON if it's a string
            if (is_string($attributeValueIds)) {
                $attributeValueIds = json_decode($attributeValueIds, true) ?? [];
            }
            unset($data['attribute_value_ids']);
            
            $product = Product::create($data);
            
            // Sync attributes if provided
            if (!empty($attributeValueIds) && is_array($attributeValueIds)) {
                $product->syncAttributes($attributeValueIds);
            }
            
            $this->success($product->toArray(), 'Product created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create product: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update product
     * PUT /api/admin/products/{id}
     */
    public function update(int $id): void {
        try {
            $product = Product::findById($id);
            
            if (!$product) {
                $this->notFound('Product not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Handle image upload if provided
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 4) . '/uploads/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Delete old image if exists
                if ($product->image_path && file_exists(dirname(__DIR__, 4) . '/' . $product->image_path)) {
                    @unlink(dirname(__DIR__, 4) . '/' . $product->image_path);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $data['image_path'] = 'uploads/products/' . $fileName;
                }
            }
            
            // Process boolean fields
            if (isset($data['is_active'])) {
                $data['is_active'] = $data['is_active'] ? 1 : 0;
            }
            if (isset($data['is_featured'])) {
                $data['is_featured'] = $data['is_featured'] ? 1 : 0;
            }
            if (isset($data['is_digital'])) {
                $data['is_digital'] = $data['is_digital'] ? 1 : 0;
            }
            if (isset($data['requires_shipping'])) {
                $data['requires_shipping'] = $data['requires_shipping'] ? 1 : 0;
            }
            
            // Process price field first (convert to float)
            if (isset($data['price'])) {
                $data['price'] = (float)$data['price'];
            }
            
            // Process nullable fields (handle empty strings as null, but preserve 0)
            if (isset($data['sale_price'])) {
                $data['sale_price'] = ($data['sale_price'] !== '' && $data['sale_price'] !== null && $data['sale_price'] !== '0') 
                    ? (float)$data['sale_price'] 
                    : ($data['sale_price'] === '0' ? 0.0 : null);
            }
            if (isset($data['wholesale_rate'])) {
                $data['wholesale_rate'] = ($data['wholesale_rate'] !== '' && $data['wholesale_rate'] !== null && $data['wholesale_rate'] !== '0') 
                    ? (float)$data['wholesale_rate'] 
                    : ($data['wholesale_rate'] === '0' ? 0.0 : null);
            }
            if (isset($data['quantity_per_carton'])) {
                $data['quantity_per_carton'] = ($data['quantity_per_carton'] !== '' && $data['quantity_per_carton'] !== null && $data['quantity_per_carton'] !== '0') 
                    ? (int)$data['quantity_per_carton'] 
                    : ($data['quantity_per_carton'] === '0' ? 0 : null);
            }
            if (isset($data['wholesale_rate_per_carton'])) {
                $data['wholesale_rate_per_carton'] = ($data['wholesale_rate_per_carton'] !== '' && $data['wholesale_rate_per_carton'] !== null && $data['wholesale_rate_per_carton'] !== '0') 
                    ? (float)$data['wholesale_rate_per_carton'] 
                    : ($data['wholesale_rate_per_carton'] === '0' ? 0.0 : null);
            }
            
            // Process list_price (always set it, allow 0)
            if (isset($data['list_price']) && $data['list_price'] !== '' && $data['list_price'] !== null) {
                // Convert to float, allow 0
                $data['list_price'] = (float)$data['list_price'];
            } elseif (isset($data['price']) && $data['price'] > 0) {
                // If list_price is not provided but price is, auto-calculate it
                $data['list_price'] = (float)$data['price'] * 5;
            }
            // Note: If list_price is not in the update data and price is not being updated, 
            // we don't modify list_price (it keeps its existing value)
            
            // Handle brand_id
            if (isset($data['brand_id'])) {
                $data['brand_id'] = !empty($data['brand_id']) ? (int)$data['brand_id'] : null;
            }
            
            // Extract attribute_value_ids before updating product
            $attributeValueIds = $data['attribute_value_ids'] ?? null;
            // Decode JSON if it's a string
            if (is_string($attributeValueIds)) {
                $attributeValueIds = json_decode($attributeValueIds, true);
            }
            unset($data['attribute_value_ids']);
            
            $product->update($data);
            
            // Sync attributes if provided
            if ($attributeValueIds !== null && is_array($attributeValueIds)) {
                $product->syncAttributes($attributeValueIds);
            }
            
            $this->success($product->toArray(), 'Product updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update product: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete product
     * DELETE /api/admin/products/{id}
     */
    public function destroy(int $id): void {
        try {
            $product = Product::findById($id);
            
            if (!$product) {
                $this->notFound('Product not found');
                return;
            }
            
            // Delete image if exists
            if ($product->image_path && file_exists(dirname(__DIR__, 4) . '/' . $product->image_path)) {
                @unlink(dirname(__DIR__, 4) . '/' . $product->image_path);
            }
            if ($product->thumb_path && file_exists(dirname(__DIR__, 4) . '/' . $product->thumb_path)) {
                @unlink(dirname(__DIR__, 4) . '/' . $product->thumb_path);
            }
            
            $product->delete();
            
            $this->success(null, 'Product deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete product: ' . $e->getMessage(), 500);
        }
    }
}

