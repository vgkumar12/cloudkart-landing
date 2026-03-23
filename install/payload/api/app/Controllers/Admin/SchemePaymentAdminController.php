<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\SchemeSubscriptionPayment;
use App\Models\SchemeSubscription;

class SchemePaymentAdminController extends Controller {
    
    /**
     * Get single payment details
     * GET /api/admin/scheme-payments/{id}
     */
    public function show(int $id): void {
        try {
            // Get payment with full details using Model
            $payment = SchemeSubscriptionPayment::findByIdWithDetails($id);
            
            if (!$payment) {
                $this->notFound('Payment not found');
                return;
            }
            
            // Get subscription details
            $subscription = SchemeSubscription::findById($payment['subscription_id'] ?? 0);
            if ($subscription) {
                $payment['subscription'] = $subscription->toArray();
            }
            
            $this->success($payment, 'Payment details retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve payment: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * List payments with filters
     * GET /api/admin/scheme-payments
     */
    public function index(): void {
        try {
            $statusFilter = $this->request->get('payment_status', 'awaiting_verification');
            $schemeFilter = (int)$this->request->get('scheme_id', 0);
            $subscriptionFilter = (int)$this->request->get('subscription_id', 0);
            $customerFilter = (int)$this->request->get('customer_id', 0);
            $limit = (int)($this->request->get('limit') ?? 200);
            
            // Use Model method
            $payments = SchemeSubscriptionPayment::getAllWithFilters(
                $statusFilter !== 'all' ? $statusFilter : null,
                $schemeFilter > 0 ? $schemeFilter : null,
                $subscriptionFilter > 0 ? $subscriptionFilter : null,
                $limit,
                true // onlyWithAmount
            );
            
            // Filter by customer if specified
            if ($customerFilter > 0) {
                $payments = array_filter($payments, function($payment) use ($customerFilter) {
                    return ($payment['customer_id'] ?? 0) === $customerFilter;
                });
                $payments = array_values($payments);
            }
            
            $this->success($payments, 'Payments retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve payments: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Mark payment as paid
     * POST /api/admin/scheme-payments/{id}/mark-paid
     */
    public function markPaid(int $id): void {
        try {
            // Get payment using Model
            $payment = SchemeSubscriptionPayment::findById($id);
            
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
            
            $this->success($payment->toArray(), 'Payment marked as paid successfully');
        } catch (\Exception $e) {
            $this->error('Failed to mark payment as paid: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Request payment re-upload
     * POST /api/admin/scheme-payments/{id}/request-reupload
     */
    public function requestReupload(int $id): void {
        try {
            // Get payment using Model
            $payment = SchemeSubscriptionPayment::findById($id);
            
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
            
            // Update payment status using Model
            $payment->update([
                'status' => 'pending',
                'uploaded_screenshot_path' => null,
                'uploaded_at' => null,
                'admin_verified_at' => null,
                'admin_verified_by' => null,
                'notes' => ($payment->notes ?? '') . "\n[Re-upload requested by admin on " . date('Y-m-d H:i:s') . "]"
            ]);
            
            // TODO: Send email notification to customer
            
            $this->success($payment->toArray(), 'Re-upload requested successfully');
        } catch (\Exception $e) {
            $this->error('Failed to request re-upload: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update payment notes
     * PUT /api/admin/scheme-payments/{id}/notes
     */
    public function updateNotes(int $id): void {
        try {
            $data = $this->request->getBody();
            $notes = trim($data['notes'] ?? '');
            
            // Get payment using Model
            $payment = SchemeSubscriptionPayment::findById($id);
            
            if (!$payment) {
                $this->notFound('Payment not found');
                return;
            }
            
            // Update notes using Model
            $payment->update(['notes' => $notes]);
            
            $this->success($payment->toArray(), 'Payment notes updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update payment notes: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update payment status
     * PUT /api/admin/scheme-payments/{id}/status
     */
    public function updateStatus(int $id): void {
        try {
            $data = $this->request->getBody();
            $status = trim($data['status'] ?? '');
            
            $validStatuses = ['pending', 'paid', 'overdue', 'awaiting_verification', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                $this->validationError(['status' => ['Invalid status. Must be one of: ' . implode(', ', $validStatuses)]], 'Validation failed');
                return;
            }
            
            // Get payment using Model
            $payment = SchemeSubscriptionPayment::findById($id);
            
            if (!$payment) {
                $this->notFound('Payment not found');
                return;
            }
            
            $updateData = ['status' => $status];
            
            // Set paid_at if marking as paid
            if ($status === 'paid' && $payment->status !== 'paid') {
                $updateData['paid_at'] = date('Y-m-d H:i:s');
                $updateData['admin_verified_at'] = date('Y-m-d H:i:s');
                $updateData['admin_verified_by'] = $this->request->user['id'] ?? null;
            }
            
            // Update status using Model
            $payment->update($updateData);
            
            $this->success($payment->toArray(), 'Payment status updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update payment status: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get payment statistics
     * GET /api/admin/scheme-payments/stats
     */
    public function stats(): void {
        try {
            // Use Model method
            $stats = SchemeSubscriptionPayment::getStats();
            
            $this->success($stats, 'Payment statistics retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve payment statistics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get recent due payments
     * GET /api/admin/scheme-payments/recent-due
     */
    public function recentDue(): void {
        try {
            $limit = (int)($this->request->get('limit') ?? 10);
            
            // Use Model method
            $payments = SchemeSubscriptionPayment::getRecentDuePayments($limit);
            
            $this->success($payments, 'Recent due payments retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve recent due payments: ' . $e->getMessage(), 500);
        }
    }
}

