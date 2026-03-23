<?php

/**
 * Slide Controller
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Slide;

class SlideController extends Controller {
    
    /**
     * Get all slides
     * GET /api/slides
     */
    public function index(): void {
        try {
            $slides = Slide::getAll();
            $this->success($slides, 'Slides retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve slides: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single slide
     * GET /api/slides/{id}
     */
    public function show(int $id): void {
        try {
            $slide = Slide::findById($id);
            
            if (!$slide) {
                $this->notFound('Slide not found');
                return;
            }
            
            $this->success($slide->toArray(), 'Slide retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve slide: ' . $e->getMessage(), 500);
        }
    }
}

