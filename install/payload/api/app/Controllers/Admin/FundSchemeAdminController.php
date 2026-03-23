<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Scheme;
use App\Models\SchemeSubscription;
use App\Models\SchemeSubscriptionPayment;
use App\Models\Customer;
use App\Models\Setting;
use App\Core\Database;

class FundSchemeAdminController extends Controller {
    
    /**
     * Get overview metrics
     */
    public function overview(): void {
        try {
            // Get scheme stats using Model
            $schemeStats = Scheme::getStats();
            
            // Get subscription stats using Model
            $subscriptionStats = SchemeSubscription::getStats();
            
            // Get payment stats using Model
            $paymentStats = SchemeSubscriptionPayment::getStats();
            
            $metrics = [
                'total_schemes' => $schemeStats['total'],
                'active_schemes' => $schemeStats['active'],
                'active_subscriptions' => $subscriptionStats['active'],
                'pending_verifications' => $paymentStats['pending_verifications'],
                'overdue_payments' => $paymentStats['overdue'],
                'total_collected' => $paymentStats['total_collected'],
                'total_outstanding' => $paymentStats['total_outstanding'],
            ];
            
            // Get recent due payments using Model
            $recentPayments = SchemeSubscriptionPayment::getRecentDuePayments(5);
            
            // Ensure subscription numbers
            foreach ($recentPayments as &$payment) {
                $payment['subscription_number'] = $this->ensureSubscriptionNumber(
                    $payment['subscription_id'] ?? 0,
                    $payment['scheme_id'] ?? 0,
                    $payment['subscription_number'] ?? null
                );
            }
            unset($payment);
            
            $this->success([
                'metrics' => $metrics,
                'recent_payments' => $recentPayments
            ], 'Overview data retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve overview: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * List subscriptions with filters
     */
    public function subscriptions(): void {
        try {
            $statusFilter = $this->request->get('subscription_status', 'all');
            $schemeFilter = (int)$this->request->get('scheme_id', 0);
            
            // Use Model method
            $subscriptions = SchemeSubscription::getAllWithPaymentStats(
                $statusFilter !== 'all' ? $statusFilter : null,
                $schemeFilter > 0 ? $schemeFilter : null,
                200
            );
            
            // Ensure subscription numbers
            foreach ($subscriptions as &$sub) {
                $sub['subscription_number'] = $this->ensureSubscriptionNumber(
                    $sub['id'] ?? 0,
                    $sub['scheme_id'] ?? 0,
                    $sub['subscription_number'] ?? null
                );
            }
            unset($sub);
            
            $this->success($subscriptions, 'Subscriptions retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve subscriptions: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * List payments with filters
     */
    public function payments(): void {
        try {
            $statusFilter = $this->request->get('payment_status', 'awaiting_verification');
            $schemeFilter = (int)$this->request->get('scheme_id', 0);
            $subscriptionFilter = (int)$this->request->get('subscription_id', 0);
            
            // Use Model method
            $payments = SchemeSubscriptionPayment::getAllWithFilters(
                $statusFilter !== 'all' ? $statusFilter : null,
                $schemeFilter > 0 ? $schemeFilter : null,
                $subscriptionFilter > 0 ? $subscriptionFilter : null,
                200,
                true // onlyWithAmount
            );
            
            // Ensure subscription numbers
            foreach ($payments as &$payment) {
                $payment['subscription_number'] = $this->ensureSubscriptionNumber(
                    $payment['subscription_id'] ?? 0,
                    $payment['scheme_id'] ?? 0,
                    $payment['subscription_number'] ?? null
                );
            }
            unset($payment);
            
            $this->success($payments, 'Payments retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve payments: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Mark payment as paid
     * POST /api/admin/fund-schemes/payments/mark-paid
     */
    public function markPaymentPaid(): void {
        try {
            $data = $this->request->getBody();
            $paymentId = (int)($data['payment_id'] ?? 0);
            
            if ($paymentId <= 0) {
                $this->validationError(['payment_id' => ['Invalid payment ID']], 'Validation failed');
                return;
            }
            
            // Get payment with customer details using Model
            $paymentRow = SchemeSubscriptionPayment::findByIdWithDetails($paymentId);
            
            if (!$paymentRow) {
                $this->notFound('Payment not found');
                return;
            }
            
            // Get payment using Model
            $payment = SchemeSubscriptionPayment::findById($paymentId);
            if (!$payment) {
                $this->notFound('Payment not found');
                return;
            }
            
            if ($payment->status === 'paid') {
                $this->error('Payment already marked as paid', 400);
                return;
            }
            
            // Update payment status using Model
            $payment->update([
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
                'admin_verified_at' => date('Y-m-d H:i:s'),
                'admin_verified_by' => $this->request->user['id'] ?? null
            ]);
            
            // TODO: Send email notification if customer email exists
            // Email sending can be added here using the notification system
            
            // Send WhatsApp Notification for Payment Received
            try {
                if (Setting::get('whatsapp_notify_payment', '0') === '1') {
                    $waService = new \App\Services\WhatsAppService();
                    
                    $phone = $paymentRow['phone'] ?? $paymentRow['customer_phone'] ?? '';
                    $name = $paymentRow['name'] ?? $paymentRow['customer_name'] ?? 'Customer';
                    
                    // Fallback to manual fetch if phone missing
                    if (empty($phone)) {
                         $sub = SchemeSubscription::findById($payment->subscription_id);
                         if ($sub) {
                             $cust = Customer::findById($sub->customer_id);
                             if ($cust) {
                                 $phone = $cust->phone;
                                 $name = $cust->name;
                             }
                         }
                    }

                    if (!empty($phone)) {
                        $paymentInfo = [
                            'customer_name' => $name,
                            'customer_phone' => $phone,
                            'amount' => $payment->amount,
                            'month' => date('F Y', strtotime($payment->due_date)),
                            'transaction_id' => $paymentRow['subscription_number'] ?? ('SUB-'.$payment->subscription_id)
                        ];
                        $waService->sendPaymentReceived($paymentInfo);
                    }
                }
            } catch (\Exception $e) {
                 error_log("WhatsApp Payment Error: " . $e->getMessage());
            }
            
            $this->success($payment->toArray(), 'Payment marked as paid successfully');
        } catch (\Exception $e) {
            $this->error('Failed to mark payment as paid: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Request payment re-upload
     * POST /api/admin/fund-schemes/payments/request-reupload
     */
    public function requestPaymentReupload(): void {
        try {
            $data = $this->request->getBody();
            $paymentId = (int)($data['payment_id'] ?? 0);
            
            if ($paymentId <= 0) {
                $this->validationError(['payment_id' => ['Invalid payment ID']], 'Validation failed');
                return;
            }
            
            // Get payment with customer details using Model
            $paymentRow = SchemeSubscriptionPayment::findByIdWithDetails($paymentId);
            
            if (!$paymentRow) {
                $this->notFound('Payment not found');
                return;
            }
            
            // Get payment using Model
            $payment = SchemeSubscriptionPayment::findById($paymentId);
            if (!$payment) {
                $this->notFound('Payment not found');
                return;
            }
            
            // Delete existing screenshot if exists
            if ($payment->uploaded_screenshot_path) {
                $existingPath = dirname(__DIR__, 2) . '/public/' . ltrim($payment->uploaded_screenshot_path, '/');
                if (is_file($existingPath)) {
                    @unlink($existingPath);
                }
            }
            
            // Reset payment to pending using Model
            $payment->update([
                'status' => 'pending',
                'uploaded_screenshot_path' => null,
                'uploaded_at' => null,
                'admin_verified_at' => null,
                'admin_verified_by' => null
            ]);
            
            // TODO: Send email notification if customer email exists
            // Email sending can be added here using the notification system
            
            $this->success($payment->toArray(), 'Payment reset to pending. Customer will need to upload a new proof.');
        } catch (\Exception $e) {
            $this->error('Failed to request payment re-upload: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * List customers with subscription stats
     */
    public function customers(): void {
        try {
            // Use Model method
            $customers = Customer::getAllWithSubscriptionStats();
            
            $this->success($customers, 'Customers retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve customers: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get fund scheme settings
     * GET /api/admin/fund-schemes/settings
     */
    public function getSettings(): void {
        try {
            $settings = [
                'fund_upi_id' => Setting::get('fund_upi_id', ''),
                'fund_upi_number' => Setting::get('fund_upi_number', ''),
                'fund_qr_path' => Setting::get('fund_qr_path', ''),
            ];
            
            $this->success($settings, 'Settings retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve settings: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update fund scheme settings
     * POST /api/admin/fund-schemes/settings
     */
    public function updateSettings(): void {
        try {
            // For multipart/form-data POST, data is in $_POST (already in $this->post)
            $upiId = trim($this->request->post('fund_upi_id', ''));
            $upiNumber = trim($this->request->post('fund_upi_number', ''));
            
            if (strlen($upiId) > 100) {
                $this->validationError(['fund_upi_id' => ['UPI ID should be 100 characters or fewer']], 'Validation failed');
                return;
            }
            if (strlen($upiNumber) > 30) {
                $this->validationError(['fund_upi_number' => ['Contact number should be 30 characters or fewer']], 'Validation failed');
                return;
            }
            
            // Handle QR code upload
            $qrPath = Setting::get('fund_qr_path', '');
            $file = $this->request->file('fund_qr');
            
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $allowedMime = ['image/png', 'image/jpeg'];
                $info = @getimagesize($file['tmp_name']);
                
                if ($info === false || !in_array($info['mime'], $allowedMime)) {
                    $this->validationError(['fund_qr' => ['Only PNG and JPG images are allowed']], 'Validation failed');
                    return;
                }
                
                $uploadDirRel = 'uploads/fund-settings/';
                $uploadDirFs = dirname(__DIR__, 2) . '/public/' . $uploadDirRel;
                
                if (!is_dir($uploadDirFs)) {
                    @mkdir($uploadDirFs, 0755, true);
                }
                
                if (!is_writable($uploadDirFs)) {
                    $this->error('Unable to write to upload directory', 500);
                    return;
                }
                
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, ['png', 'jpg', 'jpeg'])) {
                    $extension = $info['mime'] === 'image/png' ? 'png' : 'jpg';
                }
                
                $fileName = 'fund-qr-' . time() . '.' . $extension;
                $destination = $uploadDirFs . $fileName;
                
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $this->error('Failed to save uploaded QR image', 500);
                    return;
                }
                
                // Delete old QR if exists
                if ($qrPath) {
                    $oldFs = dirname(__DIR__, 2) . '/public/' . ltrim($qrPath, '/');
                    if (is_file($oldFs)) {
                        @unlink($oldFs);
                    }
                }
                
                $qrPath = $uploadDirRel . $fileName;
            }
            
            // Update settings using Model
            Setting::set('fund_upi_id', $upiId, 'string', 'Fund scheme UPI ID');
            Setting::set('fund_upi_number', $upiNumber, 'string', 'Fund scheme contact number');
            if ($qrPath) {
                Setting::set('fund_qr_path', $qrPath, 'string', 'Fund scheme QR image path');
            }
            
            $this->success([
                'fund_upi_id' => $upiId,
                'fund_upi_number' => $upiNumber,
                'fund_qr_path' => $qrPath
            ], 'Settings updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update settings: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Helper method to ensure subscription number exists
     */
    private function ensureSubscriptionNumber(int $subscriptionId, int $schemeId, ?string $existingNumber): string {
        if (!empty($existingNumber)) {
            return $existingNumber;
        }
        
        // Generate and save subscription number if missing using Model
        $subscription = SchemeSubscription::findById($subscriptionId);
        
        if ($subscription && empty($subscription->subscription_number)) {
            $newNumber = SchemeSubscription::generateSubscriptionNumber($subscription->id, $schemeId);
            $subscription->update(['subscription_number' => $newNumber]);
            return $newNumber;
        }
        
        return $subscription->subscription_number ?? 'SUB-' . $subscriptionId;
    }
    
    /**
     * Get schemes list (for fund-schemes page)
     * GET /api/admin/fund-schemes/schemes
     */
    public function schemes(): void {
        try {
            // Get all schemes (including inactive) using Model
            $schemesArray = Scheme::getAllWithInactive();
            
            $this->success($schemesArray, 'Schemes retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve schemes: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Store scheme (create)
     * POST /api/admin/fund-schemes/schemes
     */
    public function storeScheme(): void {
        try {
            $data = $this->request->getBody();
            
            // Process start_month (accept YYYY-MM or YYYY-MM-DD, coerce to first of month)
            if (!empty($data['start_month'])) {
                $raw = (string)$data['start_month'];
                if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
                    $data['start_month'] = $raw . '-01';
                } elseif (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $raw, $m)) {
                    $data['start_month'] = $m[1] . '-' . $m[2] . '-01';
                }
            }
            
            $data['frequency'] = ($data['frequency'] ?? 'monthly') === 'weekly' ? 'weekly' : 'monthly';
            $data['is_active'] = isset($data['is_active']) ? 1 : 1; // Default to active
            
            $scheme = Scheme::create($data);
            
            $this->success($scheme->toArray(), 'Scheme created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create scheme: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update scheme
     * PUT /api/admin/fund-schemes/schemes/{id}
     */
    public function updateScheme(int $id): void {
        try {
            $scheme = Scheme::findById($id);
            
            if (!$scheme) {
                $this->notFound('Scheme not found');
                return;
            }
            
            $data = $this->request->getBody();
            
            // Process start_month
            if (isset($data['start_month']) && !empty($data['start_month'])) {
                $raw = (string)$data['start_month'];
                if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
                    $data['start_month'] = $raw . '-01';
                } elseif (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $raw, $m)) {
                    $data['start_month'] = $m[1] . '-' . $m[2] . '-01';
                }
            }
            
            if (isset($data['frequency'])) {
                $data['frequency'] = $data['frequency'] === 'weekly' ? 'weekly' : 'monthly';
            }
            
            $scheme->update($data);
            
            $this->success($scheme->toArray(), 'Scheme updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update scheme: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Toggle scheme active status
     * PUT /api/admin/fund-schemes/schemes/{id}/toggle
     */
    public function toggleScheme(int $id): void {
        try {
            $scheme = Scheme::findById($id);
            
            if (!$scheme) {
                $this->notFound('Scheme not found');
                return;
            }
            
            $newStatus = $scheme->is_active ? 0 : 1;
            $scheme->update(['is_active' => $newStatus]);
            
            $this->success($scheme->toArray(), 'Scheme status updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to toggle scheme: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete scheme
     * DELETE /api/admin/fund-schemes/schemes/{id}
     */
    public function deleteScheme(int $id): void {
        try {
            $scheme = Scheme::findById($id);
            
            if (!$scheme) {
                $this->notFound('Scheme not found');
                return;
            }
            
            $scheme->delete();
            
            $this->success(null, 'Scheme deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete scheme: ' . $e->getMessage(), 500);
        }
    }
}

