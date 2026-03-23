<?php

/**
 * Home Controller
 * Handles home page data
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Slide;
use App\Models\ComboPack;

class HomeController extends Controller {
    
    /**
     * Get home page data
     * GET /api/home
     */
    public function index(): void {
        try {
            // Get slides
            $slides = Slide::getAll();
            
            // Get featured combo packs
            $comboPacks = ComboPack::getAll();
            
            $this->success([
                'slides' => $slides,
                'combo_packs' => $comboPacks
            ], 'Home data retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve home data: ' . $e->getMessage(), 500);
        }
    }
}

