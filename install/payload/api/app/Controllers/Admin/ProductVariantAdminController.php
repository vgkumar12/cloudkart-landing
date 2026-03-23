<?php

/**
 * Product Variant Admin Controller
 * Manage product variants (stock, SKU, pricing per variant)
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Attribute;
use App\Models\AttributeValue;

class ProductVariantAdminController extends Controller {
    
    /**
     * Get all variants for a product
     * GET /api/admin/products/{id}/variants
     */
    public function index(int $productId): void {
        try {
            $product = Product::findById($productId);
            
            if (!$product) {
                $this->notFound('Product not found');
                return;
            }
            
            $variants = ProductVariant::getByProduct($productId);
            
            $result = array_map(function($variant) {
                return $variant->toArray();
            }, $variants);
            
            $this->success($result, 'Variants retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve variants: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single variant
     * GET /api/admin/products/{id}/variants/{variantId}
     */
    public function show(int $productId, int $variantId): void {
        try {
            $variant = ProductVariant::findById($variantId);
            
            if (!$variant || $variant->product_id != $productId) {
                $this->notFound('Variant not found');
                return;
            }
            
            $this->success($variant->toArray(), 'Variant retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve variant: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update variant
     * PUT /api/admin/products/{id}/variants/{variantId}
     */
    public function update(int $productId, int $variantId): void {
        try {
            $variant = ProductVariant::findById($variantId);
            
            if (!$variant || $variant->product_id != $productId) {
                $this->notFound('Variant not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Only allow updating certain fields
            $allowedFields = ['sku', 'stock_quantity', 'price', 'sale_price', 'weight', 'is_active'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            if (!empty($updateData)) {
                $variant->update($updateData);
            }
            
            $this->success($variant->toArray(), 'Variant updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update variant: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate variants from product attributes
     * POST /api/admin/products/{id}/variants/generate
     */
    public function generate(int $productId): void {
        try {
            $product = Product::findById($productId);
            
            if (!$product) {
                $this->notFound('Product not found');
                return;
            }
            
            $data = $this->request->all();
            
            if (empty($data['attribute_values']) || !is_array($data['attribute_values'])) {
                $this->error('Attribute values are required', 400);
                return;
            }
            
            // Group attribute values by attribute
            $attributeGroups = [];
            foreach ($data['attribute_values'] as $valueId) {
                $value = AttributeValue::findById($valueId);
                if ($value) {
                    $attributeGroups[$value->attribute_id][] = $valueId;
                }
            }
            
            // Generate all combinations
            $combinations = $this->generateCombinations(array_values($attributeGroups));
            
            // Create variants
            $createdVariants = [];
            foreach ($combinations as $combination) {
                // Check if variant already exists
                $exists = $this->variantExists($productId, $combination);
                
                if (!$exists) {
                    // Create variant
                    $variantData = [
                        'product_id' => $productId,
                        'sku' => $this->generateVariantSku($product, $combination),
                        'stock_quantity' => 0,
                        'is_active' => true
                    ];
                    
                    $variant = ProductVariant::create($variantData);
                    
                    // Link attribute values
                    if ($variant->id) {
                        $variant->syncAttributes($combination);
                        $createdVariants[] = $variant->toArray();
                    }
                }
            }
            
            // Update product to mark it has variants
            $product->update(['has_variants' => true]);
            
            $this->success([
                'created' => count($createdVariants),
                'variants' => $createdVariants
            ], 'Variants generated successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to generate variants: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete variant
     * DELETE /api/admin/products/{id}/variants/{variantId}
     */
    public function destroy(int $productId, int $variantId): void {
        try {
            $variant = ProductVariant::findById($variantId);
            
            if (!$variant || $variant->product_id != $productId) {
                $this->notFound('Variant not found');
                return;
            }
            
            $variant->delete();
            
            // Check if product still has variants
            $remainingVariants = ProductVariant::getByProduct($productId);
            if (empty($remainingVariants)) {
                $product = Product::findById($productId);
                if ($product) {
                    $product->update(['has_variants' => false]);
                }
            }
            
            $this->success(null, 'Variant deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete variant: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate all combinations from attribute value groups
     */
    private function generateCombinations(array $arrays, int $i = 0): array {
        if (!isset($arrays[$i])) {
            return [[]];
        }
        
        $tmp = $this->generateCombinations($arrays, $i + 1);
        $result = [];
        
        foreach ($arrays[$i] as $v) {
            foreach ($tmp as $t) {
                $result[] = is_array($t) ? array_merge([$v], $t) : [$v, $t];
            }
        }
        
        return $result;
    }
    
    /**
     * Check if variant with these attributes already exists
     */
    private function variantExists(int $productId, array $attributeValueIds): bool {
        $db = \App\Core\Database::getConnection();
        
        // Get all variants for this product
        $variants = ProductVariant::getByProduct($productId);
        
        foreach ($variants as $variant) {
            $variantAttributes = $variant->getAttributes();
            $variantValueIds = array_map(function($attr) {
                return $attr['id'];
            }, $variantAttributes);
            
            sort($attributeValueIds);
            sort($variantValueIds);
            
            if ($attributeValueIds === $variantValueIds) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate SKU for variant
     */
    private function generateVariantSku(Product $product, array $attributeValueIds): string {
        $baseSku = $product->sku ?: 'PROD-' . $product->id;
        
        // Get attribute value slugs
        $slugs = [];
        foreach ($attributeValueIds as $valueId) {
            $value = AttributeValue::findById($valueId);
            if ($value) {
                $slugs[] = strtoupper(substr($value->slug, 0, 3));
            }
        }
        
        return $baseSku . '-' . implode('-', $slugs);
    }
}
