<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;

class CashfreeService
{
    private $appId;
    private $secretKey;
    private $testMode;
    private $apiUrl;

    public function __construct()
    {
        $settingModel = new Setting();
        $settings = $settingModel->getAllAsKeyValue();

        $this->appId = $settings['cashfree_app_id'] ?? '';
        $this->secretKey = $settings['cashfree_secret_key'] ?? '';
        $this->testMode = ($settings['cashfree_test_mode'] ?? '1') === '1';
        
        // Use sandbox URL for test mode
        $this->apiUrl = $this->testMode 
            ? 'https://sandbox.cashfree.com/pg'
            : 'https://api.cashfree.com/pg';
    }

    /**
     * Create Cashfree order
     */
    public function createOrder($orderId, $amount, $customerData = [])
    {
        if (empty($this->appId) || empty($this->secretKey)) {
            throw new \Exception('Cashfree credentials not configured');
        }

        $orderData = [
            'order_id' => 'CF_ORDER_' . $orderId . '_' . time(),
            'order_amount' => $amount,
            'order_currency' => 'INR',
            'customer_details' => [
                'customer_id' => 'CUST_' . ($customerData['user_id'] ?? time()),
                'customer_name' => $customerData['name'] ?? 'Customer',
                'customer_email' => $customerData['email'] ?? '',
                'customer_phone' => $customerData['phone'] ?? ''
            ],
            'order_meta' => [
                'return_url' => $this->getReturnUrl(),
                'notify_url' => $this->getWebhookUrl()
            ]
        ];

        // Make API call
        $ch = curl_init($this->apiUrl . '/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-client-id: ' . $this->appId,
            'x-client-secret: ' . $this->secretKey,
            'x-api-version: 2022-09-01'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Failed to create Cashfree order: ' . $response);
        }

        $result = json_decode($response, true);

        // Update order with Cashfree order ID
        $orderModel = new Order();
        $orderModel->updatePaymentGatewayOrderId($orderId, $result['order_id']);

        return [
            'success' => true,
            'gateway' => 'cashfree',
            'order_id' => $result['order_id'],
            'payment_session_id' => $result['payment_session_id'] ?? '',
            'order_token' => $result['order_token'] ?? '',
            'amount' => $amount,
            'test_mode' => $this->testMode
        ];
    }

    /**
     * Handle Cashfree webhook
     */
    public function handleWebhook($data)
    {
        // Verify webhook signature
        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
        $timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
        
        $expectedSignature = hash_hmac('sha256', $timestamp . json_encode($data), $this->secretKey);

        if ($signature !== $expectedSignature) {
            throw new \Exception('Invalid webhook signature');
        }

        $orderToken = $data['data']['order']['order_id'] ?? '';
        $paymentStatus = $data['data']['payment']['payment_status'] ?? '';
        
        // Extract order ID from Cashfree order ID
        preg_match('/CF_ORDER_(\d+)_/', $orderToken, $matches);
        $orderId = $matches[1] ?? null;

        if (!$orderId) {
            throw new \Exception('Invalid order ID');
        }

        $orderModel = new Order();

        if ($paymentStatus === 'SUCCESS') {
            $orderModel->updatePaymentStatus($orderId, 'cashfree', 'completed', $orderToken, $data);
            return [
                'success' => true,
                'message' => 'Payment completed successfully'
            ];
        } else {
            $orderModel->updatePaymentStatus($orderId, 'cashfree', 'failed', $orderToken, $data);
            return [
                'success' => false,
                'error' => 'Payment failed'
            ];
        }
    }

    /**
     * Get return URL
     */
    private function getReturnUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/payment-success';
    }

    /**
     * Get webhook URL
     */
    private function getWebhookUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/api/payment/cashfree/webhook';
    }
}
