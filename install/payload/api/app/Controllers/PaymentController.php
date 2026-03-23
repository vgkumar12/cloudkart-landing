<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\Setting;
use App\Services\RazorpayService;
use App\Services\PhonePeService;
use App\Services\CashfreeService;

class PaymentController extends BaseController
{
    private $razorpayService;
    private $phonepeService;
    private $cashfreeService;

    public function __construct()
    {
        parent::__construct();
        $this->razorpayService = new RazorpayService();
        $this->phonepeService = new PhonePeService();
        $this->cashfreeService = new CashfreeService();
    }

    /**
     * Get available payment methods based on settings
     */
    public function getPaymentMethods()
    {
        try {
            $settingModel = new Setting();
            $settings = $settingModel->getAllAsKeyValue();

            $paymentMode = $settings['payment_mode'] ?? 'estimate';

            // If in estimate mode, return empty array
            if ($paymentMode === 'estimate') {
                return $this->jsonResponse([
                    'payment_mode' => 'estimate',
                    'methods' => []
                ]);
            }

            $methods = [];

            // Check Cash on Delivery
            if (($settings['cod_enabled'] ?? '0') === '1') {
                $methods[] = [
                    'id' => 'cod',
                    'name' => 'Cash on Delivery',
                    'description' => 'Pay when you receive your order',
                    'icon' => 'cash',
                    'enabled' => true
                ];
            }

            // Check Razorpay
            if (($settings['razorpay_enabled'] ?? '0') === '1') {
                $methods[] = [
                    'id' => 'razorpay',
                    'name' => 'Razorpay',
                    'description' => 'Pay securely using UPI, Cards, Wallets',
                    'icon' => 'razorpay',
                    'enabled' => true,
                    'test_mode' => ($settings['razorpay_test_mode'] ?? '1') === '1'
                ];
            }

            // Check PhonePe
            if (($settings['phonepe_enabled'] ?? '0') === '1') {
                $methods[] = [
                    'id' => 'phonepe',
                    'name' => 'PhonePe',
                    'description' => 'Pay using PhonePe UPI',
                    'icon' => 'phonepe',
                    'enabled' => true,
                    'test_mode' => ($settings['phonepe_test_mode'] ?? '1') === '1'
                ];
            }

            // Check Cashfree
            if (($settings['cashfree_enabled'] ?? '0') === '1') {
                $methods[] = [
                    'id' => 'cashfree',
                    'name' => 'Cashfree',
                    'description' => 'Pay using UPI, Cards, Net Banking',
                    'icon' => 'cashfree',
                    'enabled' => true,
                    'test_mode' => ($settings['cashfree_test_mode'] ?? '1') === '1'
                ];
            }

            return $this->jsonResponse([
                'payment_mode' => 'online',
                'methods' => $methods
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate payment with selected gateway
     */
    public function initiatePayment()
    {
        try {
            $data = $this->getRequestData();
            
            $orderId = $data['order_id'] ?? null;
            $paymentMethod = $data['payment_method'] ?? null;
            $amount = $data['amount'] ?? null;

            if (!$orderId || !$paymentMethod || !$amount) {
                return $this->jsonResponse([
                    'error' => 'Missing required parameters'
                ], 400);
            }

            // Handle COD
            if ($paymentMethod === 'cod') {
                $orderModel = new Order();
                $orderModel->updatePaymentStatus($orderId, 'cod', 'pending');
                
                return $this->jsonResponse([
                    'success' => true,
                    'payment_method' => 'cod',
                    'message' => 'Order placed successfully. Pay on delivery.'
                ]);
            }

            // Handle online payment gateways
            $response = null;
            switch ($paymentMethod) {
                case 'razorpay':
                    $response = $this->razorpayService->createOrder($orderId, $amount, $data);
                    break;
                case 'phonepe':
                    $response = $this->phonepeService->initiatePayment($orderId, $amount, $data);
                    break;
                case 'cashfree':
                    $response = $this->cashfreeService->createOrder($orderId, $amount, $data);
                    break;
                default:
                    return $this->jsonResponse([
                        'error' => 'Invalid payment method'
                    ], 400);
            }

            return $this->jsonResponse($response);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify Razorpay payment
     */
    public function verifyRazorpayPayment()
    {
        try {
            $data = $this->getRequestData();
            
            $razorpayOrderId = $data['razorpay_order_id'] ?? null;
            $razorpayPaymentId = $data['razorpay_payment_id'] ?? null;
            $razorpaySignature = $data['razorpay_signature'] ?? null;
            $orderId = $data['order_id'] ?? null;

            if (!$razorpayOrderId || !$razorpayPaymentId || !$razorpaySignature || !$orderId) {
                return $this->jsonResponse([
                    'error' => 'Missing required parameters'
                ], 400);
            }

            $result = $this->razorpayService->verifyPayment(
                $razorpayOrderId,
                $razorpayPaymentId,
                $razorpaySignature,
                $orderId
            );

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PhonePe payment callback
     */
    public function phonePeCallback()
    {
        try {
            $data = $this->getRequestData();
            $result = $this->phonepeService->handleCallback($data);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cashfree webhook handler
     */
    public function cashfreeWebhook()
    {
        try {
            $data = $this->getRequestData();
            $result = $this->cashfreeService->handleWebhook($data);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
