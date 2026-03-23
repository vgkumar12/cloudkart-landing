<?php

/**
 * Product Controller
 * Handles API requests for products
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Product;
use App\Models\ProductVariant;

class ProductController extends Controller {
    
    /**
     * Get all products
     * GET /api/products
     */
    public function index(): void {
        $categoryId = $this->request->get('category_id') ? (int)$this->request->get('category_id') : null;
        $featured = $this->request->get('featured') === '1' ? true : null;
        
        try {
            $products = Product::getAll($categoryId, $featured);
            $this->success($products, 'Products retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve products: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single product
     * GET /api/products/{id}
     */
    public function show(int $id): void {
        try {
            $product = Product::findById($id);
            
            if (!$product) {
                $this->notFound('Product not found');
                return;
            }
            
            $this->success($product->toArray(), 'Product retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve product: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get product by slug
     * GET /api/products/slug/{slug}
     */
    public function showBySlug(string $slug): void {
        try {
            $product = Product::findBySlug($slug);
            
            if (!$product) {
                $this->notFound('Product not found');
                return;
            }
            
            $this->success($product->toArray(), 'Product retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve product: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get featured products
     * GET /api/products/featured
     */
    public function featured(): void {
        $limit = (int)($this->request->get('limit') ?? 10);
        
        try {
            $products = Product::getFeatured($limit);
            $this->success($products, 'Featured products retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve featured products: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Search products
     * GET /api/products/search?q={query}
     */
    public function search(): void {
        $query = $this->request->get('q', '');
        $limit = (int)($this->request->get('limit') ?? 50);
        
        if (empty($query)) {
            $this->validationError(['q' => ['Search query is required']], 'Search query is required');
            return;
        }
        
        try {
            $products = Product::search($query, $limit);
            $this->success($products, 'Search results retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to search products: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get product variants
     * GET /api/products/{id}/variants
     */
    public function getVariants(int $id): void {
        try {
            $product = Product::findById($id);
            
            if (!$product) {
                $this->notFound('Product not found');
                return;
            }
            
            if (!$product->hasVariants()) {
                $this->success([], 'No variants available');
                return;
            }
            
            $variants = ProductVariant::getByProduct($id);
            
            // Only return active variants
            $result = array_filter(array_map(function($variant) {
                if (!$variant->is_active) return null;
                
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'stock_quantity' => $variant->stock_quantity,
                    'price' => $variant->price,
                    'sale_price' => $variant->sale_price,
                    'weight' => $variant->weight,
                    'attributes' => $variant->getAttributes(),
                    'in_stock' => $variant->stock_quantity > 0
                ];
            }, $variants));
            
            $this->success(array_values($result), 'Variants retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve variants: ' . $e->getMessage(), 500);
        }
    }
}
