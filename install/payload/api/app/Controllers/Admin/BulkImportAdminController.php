<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ComboPack;
use App\Models\BulkImportLog;
use App\Core\Database;

class BulkImportAdminController extends Controller {
    
    /**
     * Process bulk import
     */
    public function import() {
        try {
            $data = $this->request->getBody();
            $importType = $data['import_type'] ?? '';
            
            if (!in_array($importType, ['categories', 'products', 'combo_packs'])) {
                return $this->json(['success' => false, 'message' => 'Invalid import type'], 400);
            }
            
            if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                return $this->json(['success' => false, 'message' => 'File upload failed'], 400);
            }
            
            $file = $_FILES['import_file'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, ['csv', 'json'])) {
                return $this->json(['success' => false, 'message' => 'Unsupported file format. Please use CSV or JSON files.'], 400);
            }
            
            // Create import log using Model
            $importLog = BulkImportLog::createLog(
                $importType,
                $file['name'],
                $file['size'],
                null // TODO: Get current user ID
            );
            $importLogId = $importLog->id;
            
            // Process file
            if ($fileExtension === 'csv') {
                $result = $this->processCSVImport($conn, $file['tmp_name'], $importType, $importLogId);
            } else {
                $result = $this->processJSONImport($conn, $file['tmp_name'], $importType, $importLogId);
            }
            
            // Normalize status
            $normalizedStatus = $result['status'];
            if ($normalizedStatus === 'completed_with_errors') {
                $normalizedStatus = 'partial';
            } elseif (!in_array($normalizedStatus, ['processing', 'completed', 'failed', 'partial'], true)) {
                $normalizedStatus = 'failed';
            }
            
            // Update import log using Model
            $importLog->updateCompletion(
                $normalizedStatus,
                $result['successful_records'],
                $result['failed_records'],
                $result['errors']
            );
            
            $this->success([
                'success' => true,
                'message' => "Import completed! {$result['successful_records']} records imported successfully, {$result['failed_records']} failed.",
                'result' => $result
            ], 'Import completed successfully');
        } catch (\Exception $e) {
            $this->error('Import failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get import history
     */
    public function history(): void {
        try {
            // Use Model method
            $history = BulkImportLog::getHistory(20);
            
            $this->success($history, 'Import history retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve import history: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Process CSV import
     */
    private function processCSVImport($conn, $filePath, $importType, $importLogId) {
        $successfulRecords = 0;
        $failedRecords = 0;
        $errors = [];
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            $rowNumber = 1;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rowNumber++;
                $rowData = array_combine($headers, $data);
                
                try {
                    switch ($importType) {
                        case 'categories':
                            $this->importCategory($conn, $rowData);
                            break;
                        case 'products':
                            $this->importProduct($conn, $rowData);
                            break;
                        case 'combo_packs':
                            $this->importComboPack($conn, $rowData);
                            break;
                    }
                    $successfulRecords++;
                } catch (\Exception $e) {
                    $failedRecords++;
                    $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                }
            }
            fclose($handle);
        }
        
        return [
            'status' => $failedRecords > 0 ? 'completed_with_errors' : 'completed',
            'successful_records' => $successfulRecords,
            'failed_records' => $failedRecords,
            'errors' => $errors
        ];
    }
    
    /**
     * Process JSON import
     */
    private function processJSONImport($conn, $filePath, $importType, $importLogId) {
        $successfulRecords = 0;
        $failedRecords = 0;
        $errors = [];
        
        $jsonData = json_decode(file_get_contents($filePath), true);
        
        if (!$jsonData) {
            throw new \Exception('Invalid JSON file');
        }
        
        foreach ($jsonData as $index => $rowData) {
            try {
                switch ($importType) {
                    case 'categories':
                        $this->importCategory($conn, $rowData);
                        break;
                    case 'products':
                        $this->importProduct($conn, $rowData);
                        break;
                    case 'combo_packs':
                        $this->importComboPack($conn, $rowData);
                        break;
                }
                $successfulRecords++;
            } catch (\Exception $e) {
                $failedRecords++;
                $errors[] = "Record " . ($index + 1) . ": " . $e->getMessage();
            }
        }
        
        return [
            'status' => $failedRecords > 0 ? 'completed_with_errors' : 'completed',
            'successful_records' => $successfulRecords,
            'failed_records' => $failedRecords,
            'errors' => $errors
        ];
    }
    
    /**
     * Import category
     */
    private function importCategory($conn, $data) {
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '');
        $description = trim($data['description'] ?? '');
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        $displayOrder = (int)($data['display_order'] ?? 0);
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        
        if (empty($name) || empty($slug)) {
            throw new \Exception('Name and slug are required');
        }
        
        // Check if category already exists using Model
        $existing = Category::findBySlug($slug);
        if ($existing) {
            throw new \Exception('Category with this slug already exists');
        }
        
        // Create category using Model
        Category::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'parent_id' => $parentId,
            'display_order' => $displayOrder,
            'is_active' => $isActive
        ]);
    }
    
    /**
     * Import product
     */
    private function importProduct($conn, $data) {
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '');
        $description = trim($data['description'] ?? '');
        $shortDescription = trim($data['short_description'] ?? '');
        $sku = trim($data['sku'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $salePrice = !empty($data['sale_price']) ? (float)$data['sale_price'] : null;
        $costPrice = !empty($data['cost_price']) ? (float)$data['cost_price'] : null;
        $stockQuantity = (int)($data['stock_quantity'] ?? 0);
        $minStockLevel = (int)($data['min_stock_level'] ?? 5);
        $maxStockLevel = (int)($data['max_stock_level'] ?? 1000);
        $weight = !empty($data['weight']) ? (float)$data['weight'] : null;
        $dimensions = trim($data['dimensions'] ?? '');
        $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $isFeatured = isset($data['is_featured']) ? (bool)$data['is_featured'] : false;
        $isDigital = isset($data['is_digital']) ? (bool)$data['is_digital'] : false;
        $requiresShipping = isset($data['requires_shipping']) ? (bool)$data['requires_shipping'] : true;
        $taxRate = (float)($data['tax_rate'] ?? 0);
        $displayOrder = (int)($data['display_order'] ?? 0);
        
        if (empty($name) || empty($slug) || $price <= 0) {
            throw new \Exception('Name, slug, and valid price are required');
        }
        
        // Check if product already exists using Model
        $existingBySlug = Product::findBySlug($slug);
        if ($existingBySlug) {
            throw new \Exception('Product with this slug already exists');
        }
        
        // Check by SKU if provided using Model
        if (!empty($sku)) {
            $existingBySku = Product::findBySku($sku);
            if ($existingBySku) {
                throw new \Exception('Product with this SKU already exists');
            }
        }
        
        // Create product using Model
        Product::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'short_description' => $shortDescription,
            'sku' => $sku,
            'price' => $price,
            'sale_price' => $salePrice,
            'cost_price' => $costPrice,
            'stock_quantity' => $stockQuantity,
            'min_stock_level' => $minStockLevel,
            'max_stock_level' => $maxStockLevel,
            'weight' => $weight,
            'dimensions' => $dimensions,
            'category_id' => $categoryId,
            'is_active' => $isActive,
            'is_featured' => $isFeatured,
            'is_digital' => $isDigital,
            'requires_shipping' => $requiresShipping,
            'tax_rate' => $taxRate,
            'display_order' => $displayOrder
        ]);
    }
    
    /**
     * Import combo pack
     */
    private function importComboPack($conn, $data) {
        $packKey = trim($data['pack_key'] ?? '');
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $youtubeUrl = trim($data['youtube_url'] ?? '');
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $displayOrder = (int)($data['display_order'] ?? 0);
        
        if (empty($packKey) || empty($name) || $price <= 0) {
            throw new \Exception('Pack key, name, and valid price are required');
        }
        
        // Check if combo pack already exists using Model
        $existing = ComboPack::findByPackKey($packKey);
        if ($existing) {
            throw new \Exception('Combo pack with this key already exists');
        }
        
        // Create combo pack using Model
        ComboPack::create([
            'pack_key' => $packKey,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'youtube_url' => $youtubeUrl,
            'is_active' => $isActive,
            'display_order' => $displayOrder
        ]);
    }
}


