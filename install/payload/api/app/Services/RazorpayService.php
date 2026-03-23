<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;

class RazorpayService
{
    private $keyId;
    private $keySecret;
    private $testMode;

    public function __construct()
    {
        $settingModel = new Setting();
        $settings = $settingModel->getAllAsKeyValue();

        $this->keyId = $settings['razorpay_key_id'] ?? '';
        $this->keySecret = $settings['razorpay_key_secret'] ?? '';
        $this->testMode = ($settings['razorpay_test_mode'] ?? '1') === '1';
    }

    /**
     * Create Razorpay order
     */
    public function createOrder($orderId, $amount, $customerData = [])
    {
        if (empty($this->keyId) || empty($this->keySecret)) {
            throw new \Exception('Razorpay credentials not configured');
        }

        // Convert amount to paise (Razorpay uses smallest currency unit)
        $amountInPaise = $amount * 100;

        $orderData = [
            'receipt' => 'order_' . $orderId,
            'amount' => $amountInPaise,
            'currency' => 'INR',
            'notes' => [
                'order_id' => $orderId,
                'customer_name' => $customerData['name'] ?? '',
                'customer_email' => $customerData['email'] ?? '',
                'customer_phone' => $customerData['phone'] ?? ''
            ]
        ];

        // Make API call to Razorpay
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $this->keyId . ':' . $this->keySecret);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Failed to create Razorpay order: ' . $response);
        }

        $razorpayOrder = json_decode($response, true);

        // Update order with Razorpay order ID
        $orderModel = new Order();
        $orderModel->updatePaymentGatewayOrderId($orderId, $razorpayOrder['id']);

        return [
            'success' => true,
            'gateway' => 'razorpay',
            'order_id' => $razorpayOrder['id'],
            'amount' => $amount,
            'currency' => 'INR',
            'key_id' => $this->keyId,
            'test_mode' => $this->testMode
        ];
    }

    /**
     * Verify Razorpay payment signature
     */
    public function verifyPayment($razorpayOrderId, $razorpayPaymentId, $razorpaySignature, $orderId)
    {
        $expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $this->keySecret);

        if ($expectedSignature === $razorpaySignature) {
            // Payment verified successfully
            $orderModel = new Order();
            $orderModel->updatePaymentStatus($orderId, 'razorpay', 'completed', $razorpayPaymentId, [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $razorpaySignature
            ]);

            return [
                'success' => true,
                'message' => 'Payment verified successfully',
                'payment_id' => $razorpayPaymentId
            ];
        } else {
            // Payment verification failed
            $orderModel = new Order();
            $orderModel->updatePaymentStatus($orderId, 'razorpay', 'failed', $razorpayPaymentId);

            return [
                'success' => false,
                'error' => 'Payment verification failed'
            ];
        }
    }
}
