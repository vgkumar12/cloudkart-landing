<?php

/**
 * Scheme Subscription Controller
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\SchemeSubscription;
use App\Models\SchemeSubscriptionPayment;

class SchemeSubscriptionController extends Controller {
    
    /**
     * Get subscriptions by customer
     * GET /api/scheme-subscriptions?customer_id={id}
     */
    public function index(): void {
        $customerId = $this->request->get('customer_id') ? (int)$this->request->get('customer_id') : null;
        
        if (!$customerId) {
            $this->validationError(['customer_id' => ['Customer ID is required']], 'Validation failed');
            return;
        }
        
        try {
            $subscriptions = SchemeSubscription::getByCustomerId($customerId);
            $this->success($subscriptions, 'Subscriptions retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve subscriptions: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create subscription
     * POST /api/scheme-subscriptions
     */
    public function store(): void {
        try {
            $userId = $this->request->user['id'] ?? null;
            
            if (!$userId) {
                $this->unauthorized('Authentication required');
                return;
            }
            
            $data = $this->request->getBody();
            $schemeId = (int)($data['scheme_id'] ?? 0);
            $name = trim($data['name'] ?? '');
            $phone = trim($data['phone'] ?? '');
            $address = trim($data['address'] ?? '');
            $userEmail = $this->request->user['email'] ?? '';
            
            // Validate required fields
            if ($schemeId <= 0) {
                $this->validationError(['scheme_id' => ['Scheme ID is required']], 'Validation failed');
                return;
            }
            if (empty($name)) {
                $this->validationError(['name' => ['Name is required']], 'Validation failed');
                return;
            }
            if (empty($phone)) {
                $this->validationError(['phone' => ['Phone is required']], 'Validation failed');
                return;
            }
            if (empty($address)) {
                $this->validationError(['address' => ['Address is required']], 'Validation failed');
                return;
            }
            
            // Get scheme
            $scheme = \App\Models\Scheme::findById($schemeId);
            if (!$scheme || !$scheme->is_active) {
                $this->notFound('Scheme not found or inactive');
                return;
            }
            
            // Get or create customer
            $customer = \App\Models\Customer::findOrCreate([
                'user_id' => $userId,
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'email' => $userEmail
            ]);
            
            // Create subscription with payment schedule
            $subscription = SchemeSubscription::createWithPaymentSchedule(
                [
                    'scheme_id' => $schemeId,
                    'customer_id' => $customer->id
                ],
                [
                    'duration_months' => $scheme->duration_months,
                    'bonus_months' => $scheme->bonus_months,
                    'amount' => $scheme->amount,
                    'frequency' => $scheme->frequency,
                    'start_month' => $scheme->start_month
                ],
                $userId
            );
            
            $this->success($subscription->toArray(), 'Subscription created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create subscription: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single subscription
     * GET /api/scheme-subscriptions/{id}
     */
    public function show(int $id): void {
        try {
            $subscription = SchemeSubscription::findById($id);
            
            if (!$subscription) {
                $this->notFound('Subscription not found');
                return;
            }
            
            $this->success($subscription->toArray(), 'Subscription retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve subscription: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get pending payments for subscription
     * GET /api/scheme-subscriptions/{id}/pending-payments
     */
    public function pendingPayments(int $id): void {
        try {
            $subscription = SchemeSubscription::findById($id);
            
            if (!$subscription) {
                $this->notFound('Subscription not found');
                return;
            }
            
            $payments = SchemeSubscriptionPayment::getPendingBySubscriptionId($id);
            $this->success($payments, 'Pending payments retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve pending payments: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Upload payment proof
     * POST /api/scheme-subscriptions/payments/{id}/upload-proof
     */
    public function uploadPaymentProof(int $id): void {
        try {
            $userId = $this->request->user['id'] ?? null;
            
            if (!$userId) {
                $this->unauthorized('Authentication required');
                return;
            }
            
            $data = $this->request->all();
            $file = $this->request->file('payment_screenshot');
            $note = trim($data['payment_note'] ?? '');
            
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $this->validationError(['payment_screenshot' => ['Payment screenshot is required']], 'Validation failed');
                return;
            }
            
            // Validate file
            $allowedTypes = ['image/jpeg', 'image/png'];
            $fileInfo = @getimagesize($file['tmp_name']);
            
            if ($fileInfo === false || !in_array($fileInfo['mime'], $allowedTypes)) {
                $this->validationError(['payment_screenshot' => ['Only JPG and PNG images are allowed']], 'Validation failed');
                return;
            }
            
            // Get payment record
            $payment = SchemeSubscriptionPayment::findById($id);
            
            if (!$payment) {
                $this->notFound('Payment record not found');
                return;
            }
            
            // Verify ownership using Model
            $payment = SchemeSubscriptionPayment::findById($id);
            if (!$payment) {
                $this->notFound('Payment record not found');
                return;
            }
            
            $subscription = SchemeSubscription::findById($payment->subscription_id);
            if (!$subscription) {
                $this->notFound('Subscription not found');
                return;
            }
            
            $customer = Customer::findById($subscription->customer_id);
            if (!$customer || $customer->user_id != $userId) {
                $this->unauthorized('You do not have permission to update this payment');
                return;
            }
            
            // Save uploaded file
            $uploadDir = __DIR__ . '/../../../public/uploads/scheme-payments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = 'payment_' . $id . '_' . time() . '.' . $extension;
            $destination = $uploadDir . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $this->error('Failed to save uploaded file', 500);
                return;
            }
            
            $relativePath = 'uploads/scheme-payments/' . $fileName;
            
            // Update payment record
            $payment->update([
                'status' => 'awaiting_verification',
                'uploaded_screenshot_path' => $relativePath,
                'uploaded_at' => date('Y-m-d H:i:s'),
                'notes' => substr(strip_tags($note), 0, 500)
            ]);
            
            $this->success($payment->toArray(), 'Payment proof uploaded successfully');
        } catch (\Exception $e) {
            $this->error('Failed to upload payment proof: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get my fund schemes (for logged-in customer)
     * GET /api/scheme-subscriptions/my-fund-schemes
     */
    public function myFundSchemes(): void {
        try {
            $userId = $this->request->user['id'] ?? null;
            
            if (!$userId) {
                $this->unauthorized('Authentication required');
                return;
            }
            
            // Get customer using Model
            $customer = \App\Models\Customer::findByUserId($userId);
            
            if (!$customer) {
                $this->notFound('Customer profile not found');
                return;
            }
            
            // Get subscriptions with payments using Model
            $subscriptions = SchemeSubscription::getByCustomerId($customer->id);
            
            // Get pending payments using Model
            $pendingPayments = SchemeSubscriptionPayment::getPendingByUserId($userId, 300);
            
            $this->success([
                'subscriptions' => $subscriptions,
                'pending_payments' => $pendingPayments
            ], 'Fund schemes retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve fund schemes: ' . $e->getMessage(), 500);
        }
    }
}

