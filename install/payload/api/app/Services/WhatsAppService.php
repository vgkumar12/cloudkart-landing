<?php

/**
 * WhatsApp Service
 * Handles WhatsApp message sending via WhatsApp Cloud API
 */

namespace App\Services;

use App\Models\Setting;

class WhatsAppService {
    
    private $apiUrl;
    private $accessToken;
    private $phoneNumberId;
    private $enabled;
    
    public function __construct() {
        $this->enabled = Setting::get('whatsapp_enabled', '0') === '1';
        $this->accessToken = Setting::get('whatsapp_access_token', '');
        $this->phoneNumberId = Setting::get('whatsapp_phone_number_id', '');
        $this->refreshUrl();
    }

    private function refreshUrl() {
        $this->apiUrl = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";
    }

    public function setCredentials(string $phoneNumberId, string $accessToken) {
        $this->phoneNumberId = $phoneNumberId;
        $this->accessToken = $accessToken;
        $this->refreshUrl();
    }

    public function setEnabled(bool $enabled) {
        $this->enabled = $enabled;
    }
    
    /**
     * Send a text message
     */
    public function sendMessage(string $to, string $message): array {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'WhatsApp is not enabled'];
        }
        
        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            return ['success' => false, 'message' => 'WhatsApp credentials not configured'];
        }
        
        // Format phone number (remove + and spaces)
        $to = preg_replace('/[^0-9]/', '', $to);
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        return $this->makeRequest($data);
    }
    
    /**
     * Send a template message
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = []): array {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'WhatsApp is not enabled'];
        }
        
        $to = preg_replace('/[^0-9]/', '', $to);
        
        $components = [];
        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(function($param) {
                    return ['type' => 'text', 'text' => $param];
                }, $parameters)
            ];
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => 'en'
                ],
                'components' => $components
            ]
        ];
        
        return $this->makeRequest($data);
    }
    
    /**
     * Send order confirmation
     */
    public function sendOrderConfirmation(array $order): array {
        $customerPhone = $order['customer_phone'] ?? '';
        if (empty($customerPhone)) {
            return ['success' => false, 'message' => 'Customer phone not provided'];
        }
        
        $message = $this->formatOrderConfirmation($order);
        return $this->sendMessage($customerPhone, $message);
    }
    
    /**
     * Send order status update
     */
    public function sendOrderStatusUpdate(array $order, string $status): array {
        $customerPhone = $order['customer_phone'] ?? '';
        if (empty($customerPhone)) {
            return ['success' => false, 'message' => 'Customer phone not provided'];
        }
        
        $message = $this->formatOrderStatus($order, $status);
        return $this->sendMessage($customerPhone, $message);
    }
    
    /**
     * Send payment reminder
     */
    public function sendPaymentReminder(array $payment): array {
        $customerPhone = $payment['customer_phone'] ?? '';
        if (empty($customerPhone)) {
            return ['success' => false, 'message' => 'Customer phone not provided'];
        }
        
        $message = $this->formatPaymentReminder($payment);
        return $this->sendMessage($customerPhone, $message);
    }

    /**
     * Send payment received confirmation
     */
    public function sendPaymentReceived(array $payment): array {
        $customerPhone = $payment['customer_phone'] ?? '';
        if (empty($customerPhone)) {
            return ['success' => false, 'message' => 'Customer phone not provided'];
        }
        
        $message = $this->formatPaymentReceived($payment);
        return $this->sendMessage($customerPhone, $message);
    }

    /**
     * Send OTP
     */
    public function sendOtp(string $phone, string $otp): array {
        $companyName = Setting::get('company_name', 'Our Store');
        $message = "Your verification code is: *{$otp}*\n\nDo not share this code with anyone.\n\n- {$companyName}";
        
        return $this->sendMessage($phone, $message);
    }
    
    /**
     * Format order confirmation message
     */
    private function formatOrderConfirmation(array $order): string {
        $companyName = Setting::get('company_name', 'Our Store');
        $customerName = $order['customer_name'] ?? 'Customer';
        $orderId = $order['id'] ?? '';
        $itemCount = $order['quantity'] ?? 0;
        $totalAmount = $order['total_amount'] ?? 0;
        
        return "Hi {$customerName}! 🎉\n\n" .
               "Your order #{$orderId} has been confirmed!\n\n" .
               "📦 Items: {$itemCount}\n" .
               "💰 Total: ₹" . number_format($totalAmount, 2) . "\n\n" .
               "We'll notify you once it's shipped.\n\n" .
               "Thank you for shopping with us!\n" .
               "- {$companyName}";
    }
    
    /**
     * Format order status message
     */
    private function formatOrderStatus(array $order, string $status): string {
        $companyName = Setting::get('company_name', 'Our Store');
        $customerName = $order['customer_name'] ?? 'Customer';
        $orderId = $order['id'] ?? '';
        
        $statusMessages = [
            'processing' => "Hi {$customerName}!\n\nYour order #{$orderId} is being processed. We'll update you soon!\n\n- {$companyName}",
            'shipped' => "Great news {$customerName}! 📦\n\nYour order #{$orderId} has been shipped!\n\n🚚 It will be delivered soon.\n\n- {$companyName}",
            'delivered' => "Hi {$customerName}! ✅\n\nYour order #{$orderId} has been delivered!\n\nThank you for shopping with us!\n\n- {$companyName}",
            'cancelled' => "Hi {$customerName},\n\nYour order #{$orderId} has been cancelled.\n\nIf you have any questions, please contact us.\n\n- {$companyName}"
        ];
        
        return $statusMessages[$status] ?? "Order #{$orderId} status: {$status}";
    }
    
    /**
     * Format payment reminder message
     */
    private function formatPaymentReminder(array $payment): string {
        $companyName = Setting::get('company_name', 'Our Store');
        $customerName = $payment['customer_name'] ?? 'Customer';
        $amount = $payment['amount'] ?? 0;
        $dueDate = $payment['due_date'] ?? '';
        
        return "Hi {$customerName},\n\n" .
               "Reminder: Your scheme payment of ₹" . number_format($amount, 2) . " is due on {$dueDate}.\n\n" .
               "Please make the payment to avoid any inconvenience.\n\n" .
               "- {$companyName}";
    }

    /**
     * Format payment received message
     */
    private function formatPaymentReceived(array $payment): string {
        $companyName = Setting::get('company_name', 'Our Store');
        $customerName = $payment['customer_name'] ?? 'Customer';
        $amount = $payment['amount'] ?? 0;
        $month = $payment['month'] ?? ''; 
        $transactionId = $payment['transaction_id'] ?? '';
        
        return "Hi {$customerName},\n\n" .
               "Payment Received! ✅\n\n" .
               "We have received your payment of ₹" . number_format($amount, 2) . " for {$month}.\n" .
               "Ref: {$transactionId}\n\n" .
               "Thank you!\n" .
               "- {$companyName}";
    }
    
    /**
     * Make API request
     */
    private function makeRequest(array $data): array {
        $ch = curl_init($this->apiUrl);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'message' => 'cURL error: ' . $error
            ];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['messages'])) {
            // Log successful message
            $this->logMessage($data['to'], $data, $responseData);
            
            return [
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $responseData
            ];
        }
        
        return [
            'success' => false,
            'message' => $responseData['error']['message'] ?? 'Failed to send message',
            'data' => $responseData
        ];
    }
    
    /**
     * Log message to database
     */
    private function logMessage(string $to, array $request, array $response): void {
        // TODO: Implement message logging to database
        error_log("WhatsApp message sent to {$to}: " . json_encode($response));
    }
}
