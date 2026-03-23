<?php

/**
 * Attribute Admin Controller
 * Admin CRUD operations for attributes and attribute values
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;

class AttributeAdminController extends Controller {
    
    /**
     * Get all attributes with values
     * GET /api/admin/attributes
     */
    public function index(): void {
        try {
            $attributes = Attribute::getAllWithValues();
            $this->success($attributes, 'Attributes retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve attributes: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single attribute with values
     * GET /api/admin/attributes/{id}
     */
    public function show(int $id): void {
        try {
            $attribute = Attribute::findById($id);
            
            if (!$attribute) {
                $this->notFound('Attribute not found');
                return;
            }
            
            // Get attribute values
            $values = AttributeValue::getByAttribute($id);
            
            $result = $attribute->toArray();
            $result['values'] = array_map(function($value) {
                return $value->toArray();
            }, $values);
            
            $this->success($result, 'Attribute retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve attribute: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create attribute
     * POST /api/admin/attributes
     */
    public function store(): void {
        try {
            $data = $this->request->all();
            
            // Validate required fields
            if (empty($data['name'])) {
                $this->error('Attribute name is required', 400);
                return;
            }
            
            // Auto-generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['name']);
            }
            
            // Extract values array if present (handle separately)
            $values = null;
            if (isset($data['values'])) {
                $values = $data['values'];
                unset($data['values']); // Remove from data to avoid SQL error
            }
            
            // Set defaults
            $data['type'] = $data['type'] ?? 'select';
            $data['is_required'] = isset($data['is_required']) ? (bool)$data['is_required'] : false;
            $data['is_filterable'] = isset($data['is_filterable']) ? (bool)$data['is_filterable'] : true;
            $data['is_visible'] = isset($data['is_visible']) ? (bool)$data['is_visible'] : true;
            $data['display_order'] = isset($data['display_order']) ? (int)$data['display_order'] : 0;
            
            $attribute = Attribute::create($data);
            
            // Handle values if provided
            if ($values !== null && is_array($values) && $attribute->id) {
                foreach ($values as $value) {
                    if (empty($value['value'])) continue;
                    
                    // Auto-generate slug if not provided
                    if (empty($value['slug'])) {
                        $value['slug'] = $this->generateSlug($value['value']);
                    }
                    
                    $valueData = [
                        'attribute_id' => $attribute->id,
                        'value' => $value['value'],
                        'slug' => $value['slug'],
                        'color_code' => $value['color_code'] ?? null,
                        'weight_value' => isset($value['weight_value']) ? (float)$value['weight_value'] : null,
                        'display_order' => isset($value['display_order']) ? (int)$value['display_order'] : 0
                    ];
                    
                    AttributeValue::create($valueData);
                }
            }
            
            $this->success($attribute->toArray(), 'Attribute created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create attribute: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update attribute
     * PUT /api/admin/attributes/{id}
     */
    public function update(int $id): void {
        try {
            $attribute = Attribute::findById($id);
            
            if (!$attribute) {
                $this->notFound('Attribute not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Extract values array if present (handle separately)
            $values = null;
            if (isset($data['values'])) {
                $values = $data['values'];
                unset($data['values']); // Remove from data to avoid SQL error
            }
            
            // Process boolean fields
            if (isset($data['is_required'])) {
                $data['is_required'] = (bool)$data['is_required'];
            }
            if (isset($data['is_filterable'])) {
                $data['is_filterable'] = (bool)$data['is_filterable'];
            }
            if (isset($data['is_visible'])) {
                $data['is_visible'] = (bool)$data['is_visible'];
            }
            
            // Update attribute
            $attribute->update($data);
            
            // Handle values if provided
            if ($values !== null && is_array($values)) {
                // Delete existing values
                $db = \App\Core\Database::getConnection();
                $stmt = $db->prepare("DELETE FROM attribute_values WHERE attribute_id = ?");
                $stmt->execute([$id]);
                
                // Insert new values
                foreach ($values as $value) {
                    if (empty($value['value'])) continue;
                    
                    // Auto-generate slug if not provided
                    if (empty($value['slug'])) {
                        $value['slug'] = $this->generateSlug($value['value']);
                    }
                    
                    $valueData = [
                        'attribute_id' => $id,
                        'value' => $value['value'],
                        'slug' => $value['slug'],
                        'color_code' => $value['color_code'] ?? null,
                        'weight_value' => isset($value['weight_value']) ? (float)$value['weight_value'] : null,
                        'display_order' => isset($value['display_order']) ? (int)$value['display_order'] : 0
                    ];
                    
                    AttributeValue::create($valueData);
                }
            }
            
            $this->success($attribute->toArray(), 'Attribute updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update attribute: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete attribute
     * DELETE /api/admin/attributes/{id}
     */
    public function destroy(int $id): void {
        try {
            $attribute = Attribute::findById($id);
            
            if (!$attribute) {
                $this->notFound('Attribute not found');
                return;
            }
            
            $attribute->delete();
            
            $this->success(null, 'Attribute deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete attribute: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Add value to attribute
     * POST /api/admin/attributes/{id}/values
     */
    public function addValue(int $id): void {
        try {
            $attribute = Attribute::findById($id);
            
            if (!$attribute) {
                $this->notFound('Attribute not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Validate required fields
            if (empty($data['value'])) {
                $this->error('Value is required', 400);
                return;
            }
            
            // Auto-generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['value']);
            }
            
            $data['attribute_id'] = $id;
            $data['display_order'] = isset($data['display_order']) ? (int)$data['display_order'] : 0;
            
            // Handle weight_value for Package Size attributes
            if (isset($data['weight_value'])) {
                $data['weight_value'] = (float)$data['weight_value'];
            }
            
            $attributeValue = AttributeValue::create($data);
            
            $this->success($attributeValue->toArray(), 'Attribute value added successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to add attribute value: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update attribute value
     * PUT /api/admin/attributes/{id}/values/{valueId}
     */
    public function updateValue(int $id, int $valueId): void {
        try {
            $attributeValue = AttributeValue::findById($valueId);
            
            if (!$attributeValue || $attributeValue->attribute_id != $id) {
                $this->notFound('Attribute value not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Handle weight_value for Package Size attributes
            if (isset($data['weight_value'])) {
                $data['weight_value'] = (float)$data['weight_value'];
            }
            
            $attributeValue->update($data);
            
            $this->success($attributeValue->toArray(), 'Attribute value updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update attribute value: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete attribute value
     * DELETE /api/admin/attributes/{id}/values/{valueId}
     */
    public function deleteValue(int $id, int $valueId): void {
        try {
            $attributeValue = AttributeValue::findById($valueId);
            
            if (!$attributeValue || $attributeValue->attribute_id != $id) {
                $this->notFound('Attribute value not found');
                return;
            }
            
            $attributeValue->delete();
            
            $this->success(null, 'Attribute value deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete attribute value: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate slug from text
     */
    private function generateSlug(string $text): string {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
