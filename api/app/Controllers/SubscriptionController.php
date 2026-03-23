<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Store;
use App\Models\Plan;

class SubscriptionController {
    public function checkout() {
        $request = new Request();
        $response = new Response();

        $userId = $request->input('user_id');
        $planId = $request->input('plan_id') ?: 1; // Default to Starter if not provided
        $storeName = $request->input('store_name');
        $subdomain = $request->input('subdomain');
        $customDomain = $request->input('custom_domain');
        $adminPassword = $request->input('password'); // From registration form
        $adminName = $request->input('name'); // From registration form
        $adminEmail = $request->input('email'); // From registration form
        $adminPhone = $request->input('phone'); // Capture phone
        $theme = $request->input('theme') ?: 'general'; // Capture theme
        $tagline = $request->input('tagline'); // Capture tagline
        $brandColor      = $request->input('brand_color')      ?? '';
        $headerStyle     = $request->input('header_style')     ?? 'light';
        $businessAddress = $request->input('business_address') ?? '';
        $businessHours   = $request->input('business_hours')   ?? '';
        $facebookUrl     = $request->input('facebook_url')     ?? '';
        $instagramUrl    = $request->input('instagram_url')    ?? '';
        $whatsappNumber  = $request->input('whatsapp_number')  ?? '';

        $missing = [];
        if (!$userId) $missing[] = 'user_id';
        if (!$storeName) $missing[] = 'store_name';
        if (!$subdomain) $missing[] = 'subdomain';

        if (!empty($missing)) {
            $response->error("Missing checkout details: " . implode(', ', $missing));
        }

        // Subdomain Validation
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $subdomain)) {
            $response->error("Invalid subdomain format. Use only letters, numbers and hyphens.");
        }

        if (Store::findBySubdomain($subdomain)) {
            $response->error("Subdomain '{$subdomain}' is already taken. Please choose another.");
        }

        $storeId = null;
        $tablePrefix = 'ck_' . str_replace('-', '_', $subdomain) . '_';

        try {
            $plan = Plan::find($planId);
            if (!$plan) {
                $response->error("Invalid plan");
            }

            // 1. Create store record
            $storeId = Store::create([
                'user_id' => $userId,
                'store_name' => $storeName,
                'subdomain' => $subdomain,
                'table_prefix' => $tablePrefix,
                'theme' => $theme,
                'custom_domain' => $customDomain,
                'plan_id' => $planId,
                'status' => 'trial',
                'is_trial' => 1,
                'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
            ]);

            // 2. Call Provisioning Service
            $provisioner = new \App\Services\StoreProvisioningService();
            
            // Handle Logo upload if present
            $logoPath = null;
            $logoName = null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logoPath = $_FILES['logo']['tmp_name'];
                $logoName = $_FILES['logo']['name'];
            }

            $setupResult = $provisioner->provisionStore([
                'store_name' => $storeName,
                'subdomain' => $subdomain,
                'table_prefix' => $tablePrefix,
                'theme' => $theme,
                'brand_color'     => $brandColor,
                'header_style'    => $headerStyle,
                'logo_tmp'        => $logoPath,
                'logo_name'       => $logoName,
                'tagline'         => $tagline,
                'business_address'=> $businessAddress,
                'business_hours'  => $businessHours,
                'facebook_url'    => $facebookUrl,
                'instagram_url'   => $instagramUrl,
                'whatsapp_number' => $whatsappNumber
            ], [
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $adminPassword,
                'phone' => $adminPhone
            ]);

            // 3. Create initial licence (Trial - 7 Days)
            $licenceKey = bin2hex(random_bytes(16));
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("INSERT INTO platform_licences (store_id, licence_key, status, expires_at) VALUES (?, ?, 'trial', DATE_ADD(NOW(), INTERVAL 7 DAY))");
            $stmt->execute([$storeId, $licenceKey]);

            // Send welcome email (non-blocking)
            try {
                \App\Services\PlatformMailer::sendWelcome(
                    $adminEmail,
                    $adminName,
                    $storeName,
                    $setupResult['url'],
                    $setupResult['url'] . '/admin'
                );
            } catch (\Throwable $e) { /* silent — email never blocks provisioning */ }

            $response->success([
                'store_id' => $storeId,
                'store_url' => $setupResult['url'],
                'admin_url' => $setupResult['url'] . '/admin',
                'status' => 'active'
            ], "Store provisioned successfully");

        } catch (\Exception $e) {
            // ROLLBACK
            if ($storeId) {
                Store::deleteById($storeId);
            }
            if (isset($provisioner)) {
                $provisioner->rollback($subdomain, $tablePrefix);
            }
            $response->error("Provisioning failed: " . $e->getMessage());
        }
    }

    public function verify() {
        $request = new Request();
        $response = new Response();
        
        $storeId = $request->input('store_id');
        $licenceKey = bin2hex(random_bytes(16)); // Generate UUID-like key

        try {
            $db = \App\Core\Database::getConnection();
            
            // 1. Update store status
            $stmt = $db->prepare("UPDATE platform_stores SET status = 'active' WHERE id = ?");
            $stmt->execute([$storeId]);

            // 2. Create licence record
            $stmt = $db->prepare("INSERT INTO platform_licences (store_id, licence_key, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 YEAR))");
            $stmt->execute([$storeId, $licenceKey]);

            $response->success([
                'status' => 'active',
                'licence_key' => $licenceKey
            ], "Subscription activated");
        } catch (\Exception $e) {
            $response->error("Verification failed: " . $e->getMessage());
        }
    }
}
