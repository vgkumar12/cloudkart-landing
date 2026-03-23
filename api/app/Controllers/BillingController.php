<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Models\Store;
use App\Models\Plan;

class BillingController {
    public function info() {
        $request = new Request();
        $response = new Response();
        $storeId = $request->input('store_id');

        if (!$storeId) {
            $response->error("Store ID required");
        }

        try {
            $db = Database::getConnection();
            
            // Get Store & Plan
            $stmt = $db->prepare("SELECT s.*, p.name as plan_name, p.price_one_time 
                                 FROM platform_stores s 
                                 JOIN platform_plans p ON s.plan_id = p.id 
                                 WHERE s.id = ?");
            $stmt->execute([$storeId]);
            $store = $stmt->fetch();

            if (!$store) {
                $response->error("Store not found");
            }

            // Get Licence
            $stmt = $db->prepare("SELECT * FROM platform_licences WHERE store_id = ? AND status != 'revoked' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$storeId]);
            $licence = $stmt->fetch();

            // Get Invoices
            $stmt = $db->prepare("SELECT * FROM platform_invoices WHERE store_id = ? ORDER BY created_at DESC");
            $stmt->execute([$storeId]);
            $invoices = $stmt->fetchAll();

            $response->success([
                'store' => $store,
                'licence' => $licence,
                'invoices' => $invoices,
                'plans' => Plan::getActive()
            ]);
        } catch (\Exception $e) {
            $response->error($e->getMessage());
        }
    }

    public function initiatePayment() {
        $request = new Request();
        $response = new Response();
        $storeId = $request->input('store_id');
        $planId = $request->input('plan_id');

        if (!$storeId || !$planId) {
            $response->error("Store ID and Plan ID required");
        }

        try {
            $db = Database::getConnection();
            $plan = Plan::find($planId);
            if (!$plan) $response->error("Invalid plan");

            // In a real scenario, we would use Razorpay/Cashfree SDK here
            // to create an order and return the order_id.
            // For now, we simulate the order creation.

            $orderId = "order_" . bin2hex(random_bytes(8));
            
            $response->success([
                'order_id' => $orderId,
                'amount' => $plan['price_one_time'],
                'currency' => 'INR',
                'key_id' => 'rzp_test_placeholder' // Replace with real key in production
            ]);
        } catch (\Exception $e) {
            $response->error($e->getMessage());
        }
    }

    public function webhook() {
        // Handle Razorpay/Cashfree Webhook
        // 1. Verify Signature
        // 2. Identify Store and Plan from notes
        // 3. Update platform_stores (status=active, is_trial=0, plan_id=new)
        // 4. Update platform_licences (status=active, expires_at = +1 year)
        // 5. Generate platform_invoices record
        
        $response = new Response();
        $response->success([], "Webhook received");
    }
}
