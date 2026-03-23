<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;

class PhonePeService
{
    private $merchantId;
    private $saltKey;
    private $saltIndex;
    private $testMode;
    private $apiUrl;

    public function __construct()
    {
        $settingModel = new Setting();
        $settings = $settingModel->getAllAsKeyValue();

        $this->merchantId = $settings['phonepe_merchant_id'] ?? '';
        $this->saltKey = $settings['phonepe_salt_key'] ?? '';
        $this->saltIndex = $settings['phonepe_salt_index'] ?? '1';
        $this->testMode = ($settings['phonepe_test_mode'] ?? '1') === '1';
        
        // Use UAT URL for test mode, production URL otherwise
        $this->apiUrl = $this->testMode 
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
            : 'https://api.phonepe.com/apis/hermes';
    }

    /**
     * Initiate PhonePe payment
     */
    public function initiatePayment($orderId, $amount, $customerData = [])
    {
        if (empty($this->merchantId) || empty($this->saltKey)) {
            throw new \Exception('PhonePe credentials not configured');
        }

        // Convert amount to paise
        $amountInPaise = $amount * 100;

        $transactionId = 'TXN_' . $orderId . '_' . time();
        
        $payload = [
            'merchantId' => $this->merchantId,
            'merchantTransactionId' => $transactionId,
            'merchantUserId' => 'USER_' . ($customerData['user_id'] ?? time()),
            'amount' => $amountInPaise,
            'redirectUrl' => $this->getCallbackUrl(),
            'redirectMode' => 'POST',
            'callbackUrl' => $this->getCallbackUrl(),
            'mobileNumber' => $customerData['phone'] ?? '',
            'paymentInstrument' => [
                'type' => 'PAY_PAGE'
            ]
        ];

        $base64Payload = base64_encode(json_encode($payload));
        $checksum = hash('sha256', $base64Payload . '/pg/v1/pay' . $this->saltKey) . '###' . $this->saltIndex;

        // Make API call
        $ch = curl_init($this->apiUrl . '/pg/v1/pay');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['request' => $base64Payload]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-VERIFY: ' . $checksum
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Failed to initiate PhonePe payment: ' . $response);
        }

        $result = json_decode($response, true);

        if ($result['success'] ?? false) {
            // Update order with transaction ID
            $orderModel = new Order();
            $orderModel->updatePaymentGatewayOrderId($orderId, $transactionId);

            return [
                'success' => true,
                'gateway' => 'phonepe',
                'redirect_url' => $result['data']['instrumentResponse']['redirectInfo']['url'] ?? '',
                'transaction_id' => $transactionId,
                'test_mode' => $this->testMode
            ];
        } else {
            throw new \Exception('PhonePe payment initiation failed: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    /**
     * Handle PhonePe callback
     */
    public function handleCallback($data)
    {
        $base64Response = $data['response'] ?? '';
        $receivedChecksum = $data['checksum'] ?? '';

        // Verify checksum
        $expectedChecksum = hash('sha256', $base64Response . $this->saltKey) . '###' . $this->saltIndex;

        if ($receivedChecksum !== $expectedChecksum) {
            throw new \Exception('Invalid checksum');
        }

        $response = json_decode(base64_decode($base64Response), true);

        $transactionId = $response['data']['merchantTransactionId'] ?? '';
        $paymentState = $response['data']['state'] ?? '';
        
        // Extract order ID from transaction ID
        preg_match('/TXN_(\d+)_/', $transactionId, $matches);
        $orderId = $matches[1] ?? null;

        if (!$orderId) {
            throw new \Exception('Invalid transaction ID');
        }

        $orderModel = new Order();

        if ($paymentState === 'COMPLETED') {
            $orderModel->updatePaymentStatus($orderId, 'phonepe', 'completed', $transactionId, $response);
            return [
                'success' => true,
                'message' => 'Payment completed successfully',
                'transaction_id' => $transactionId
            ];
        } else {
            $orderModel->updatePaymentStatus($orderId, 'phonepe', 'failed', $transactionId, $response);
            return [
                'success' => false,
                'error' => 'Payment failed or cancelled',
                'transaction_id' => $transactionId
            ];
        }
    }

    /**
     * Get callback URL
     */
    private function getCallbackUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/api/payment/phonepe/callback';
    }
}
