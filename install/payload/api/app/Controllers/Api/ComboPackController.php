<?php

/**
 * Combo Pack Controller
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\ComboPack;

class ComboPackController extends Controller {
    
    /**
     * Get all combo packs
     * GET /api/combo-packs
     */
    public function index(): void {
        try {
            $comboPacks = ComboPack::getAll();
            $this->success($comboPacks, 'Combo packs retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve combo packs: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single combo pack
     * GET /api/combo-packs/{id}
     */
    public function show(int $id): void {
        try {
            $comboPack = ComboPack::findById($id);
            
            if (!$comboPack) {
                $this->notFound('Combo pack not found');
                return;
            }
            
            $this->success($comboPack->toArray(), 'Combo pack retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve combo pack: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get combo pack by pack_key
     * GET /api/combo-packs/key/{packKey}
     */
    public function showByPackKey(string $packKey): void {
        try {
            $comboPack = ComboPack::findByPackKey($packKey);
            
            if (!$comboPack) {
                $this->notFound('Combo pack not found');
                return;
            }
            
            $this->success($comboPack->toArray(), 'Combo pack retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve combo pack: ' . $e->getMessage(), 500);
        }
    }
}

