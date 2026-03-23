<?php

/**
 * Setting Controller
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Setting;
use App\Helpers\SettingsHelper;

class SettingController extends Controller {
    
    public function index(): void {
        try {
            // Get public settings from database
            $settings = Setting::getPublicSettings();
            
            // Return as key-value object for frontend
            $this->success($settings, 'Public settings retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve settings: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Detect setting type from value
     */
    private function detectType($value): string {
        if (is_bool($value)) return 'boolean';
        if (is_int($value) || is_float($value)) return 'number';
        if (is_array($value)) return 'json';
        return 'string';
    }
    
    /**
     * Get single setting
     * GET /api/settings/{key}
     */
    public function show(string $key): void {
        try {
            $value = Setting::get($key);
            
            if ($value === null) {
                $this->notFound('Setting not found');
                return;
            }
            
            $this->success(['key' => $key, 'value' => $value], 'Setting retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve setting: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update setting
     * PUT /api/settings/{key}
     */
    public function update(string $key): void {
        try {
            $value = $this->request->input('value');
            $group = $this->request->input('group', 'general');
            $type = $this->request->input('type', 'string');
            $isPublic = (bool)$this->request->input('is_public', true);
            
            if ($value === null) {
                $this->validationError(['value' => ['Value is required']], 'Validation failed');
                return;
            }
            
            Setting::set($key, $value, $group, $type, $isPublic);
            
            $this->success(['key' => $key, 'value' => Setting::get($key)], 'Setting updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update setting: ' . $e->getMessage(), 500);
        }
    }
}



