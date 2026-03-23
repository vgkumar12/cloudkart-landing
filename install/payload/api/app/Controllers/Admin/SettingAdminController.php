<?php

/**
 * Setting Admin Controller
 * Admin operations for settings (uses existing Setting model)
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Setting;

class SettingAdminController extends Controller {
    
    public function index(): void {
        try {
            $settings = Setting::getAllGrouped();
            $this->success($settings, 'Settings retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve settings: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update multiple settings
     * PUT /api/admin/settings
     */
    public function updateAll(): void {
        try {
            $data = $this->request->all(); // Expected: [ {key: '...', value: '...', group: '...', type: '...'} ]
            
            if (!is_array($data)) {
                $this->error('Invalid data format', 400);
                return;
            }

            foreach ($data as $item) {
                if (isset($item['key']) && isset($item['value'])) {
                    Setting::set(
                        $item['key'], 
                        $item['value'], 
                        $item['group'] ?? 'general', 
                        $item['type'] ?? 'string',
                        $item['is_public'] ?? true
                    );
                }
            }
            
            // Increment settings_version to invalidate frontend cache
            $currentVersion = (int)Setting::get('settings_version', 0);
            Setting::set('settings_version', $currentVersion + 1, 'general', 'number', true);
            
            $this->success(null, 'Settings updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update settings: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update single setting
     * PUT /api/admin/settings/{key}
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
            
            // Increment settings_version to invalidate frontend cache (unless updating version itself)
            if ($key !== 'settings_version') {
                $currentVersion = (int)Setting::get('settings_version', 0);
                Setting::set('settings_version', $currentVersion + 1, 'general', 'number', true);
            }
            
            $this->success(['key' => $key, 'value' => Setting::get($key)], 'Setting updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update setting: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Bulk update settings (for payment settings page)
     * POST /api/admin/settings/bulk-update
     */
    public function bulkUpdate(): void {
        try {
            $data = $this->request->input('settings');
            
            if (!is_array($data)) {
                $this->error('Invalid data format. Expected settings array.', 400);
                return;
            }

            foreach ($data as $key => $value) {
                // Determine group based on key prefix
                $group = 'payment';
                if (strpos($key, 'payment_') === 0 || strpos($key, 'razorpay_') === 0 || 
                    strpos($key, 'phonepe_') === 0 || strpos($key, 'cashfree_') === 0 || 
                    strpos($key, 'cod_') === 0) {
                    $group = 'payment';
                }
                
                // Determine type
                $type = 'text';
                if (strpos($key, '_enabled') !== false || strpos($key, '_test_mode') !== false) {
                    $type = 'boolean';
                }
                
                // Determine if public (payment credentials should not be public)
                $isPublic = !in_array($key, ['razorpay_key_secret', 'phonepe_salt_key', 'cashfree_secret_key']);
                
                Setting::set($key, $value, $group, $type, $isPublic);
            }
            
            // Increment settings_version to invalidate frontend cache
            $currentVersion = (int)Setting::get('settings_version', 0);
            Setting::set('settings_version', $currentVersion + 1, 'general', 'number', true);
            
            $this->success(null, 'Payment settings updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update payment settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test WhatsApp connection
     * POST /api/admin/test-whatsapp
     */
    public function testWhatsApp(): void {
        try {
            $phone = $this->request->input('phone');
            $settings = $this->request->input('settings');
            
            if (empty($phone)) {
                $this->error('Phone number is required', 400);
                return;
            }
            
            $service = new \App\Services\WhatsAppService();
            // Allow manual configuration override
            if (is_array($settings)) {
                $service->setCredentials(
                    $settings['whatsapp_phone_number_id'] ?? '', 
                    $settings['whatsapp_access_token'] ?? ''
                );
                $service->setEnabled(true);
            }
            
            $result = $service->sendMessage($phone, "✅ WhatsApp connection test successful! \n\nThis is a test message from your Suncrackers admin panel.");
            
            if ($result['success']) {
                $this->success($result['data'] ?? [], 'Test message sent successfully');
            } else {
                $this->error($result['message'] ?? 'Failed to send message', 500);
            }
            
        } catch (\Exception $e) {
            $this->error('Error sending test message: ' . $e->getMessage(), 500);
        }
    }
}



