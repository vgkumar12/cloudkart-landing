<?php

/**
 * Scheme Subscription Payment Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class SchemeSubscriptionPayment extends Model {
    protected string $table = 'scheme_subscription_payments';
    
    public ?int $id = null;
    public ?int $subscription_id = null;
    public ?int $period_index = null;
    public ?string $due_date = null;
    public ?float $amount_due = null;
    public ?string $status = null;
    public ?string $paid_at = null;
    public ?string $uploaded_screenshot_path = null;
    public ?string $uploaded_at = null;
    public ?string $admin_verified_at = null;
    public ?int $admin_verified_by = null;
    public ?string $notes = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get payments by subscription ID
     */
    public static function getBySubscriptionId(int $subscriptionId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM scheme_subscription_payments WHERE subscription_id = ? ORDER BY period_index ASC");
        $stmt->execute([$subscriptionId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Get pending payments by subscription ID
     */
    public static function getPendingBySubscriptionId(int $subscriptionId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM scheme_subscription_payments WHERE subscription_id = ? AND status IN ('pending', 'awaiting_verification', 'overdue') ORDER BY period_index ASC");
        $stmt->execute([$subscriptionId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Find payment by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM scheme_subscription_payments WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get payment statistics
     */
    public static function getStats(): array {
        $conn = Database::getConnection();
        
        $pendingVerificationsStmt = $conn->query("SELECT COUNT(*) FROM scheme_subscription_payments WHERE status = 'awaiting_verification'");
        $pendingVerifications = (int)$pendingVerificationsStmt->fetchColumn();
        
        $overdueStmt = $conn->query("SELECT COUNT(*) FROM scheme_subscription_payments WHERE status = 'overdue'");
        $overdue = (int)$overdueStmt->fetchColumn();
        
        $totalCollectedStmt = $conn->query("SELECT COALESCE(SUM(amount_due),0) FROM scheme_subscription_payments WHERE status = 'paid'");
        $totalCollected = (float)$totalCollectedStmt->fetchColumn();
        
        $totalOutstandingStmt = $conn->query("SELECT COALESCE(SUM(amount_due),0) FROM scheme_subscription_payments WHERE status IN ('pending','overdue','awaiting_verification')");
        $totalOutstanding = (float)$totalOutstandingStmt->fetchColumn();
        
        return [
            'pending_verifications' => $pendingVerifications,
            'overdue' => $overdue,
            'total_collected' => $totalCollected,
            'total_outstanding' => $totalOutstanding
        ];
    }
    
    /**
     * Get recent due payments
     */
    public static function getRecentDuePayments(int $limit = 5): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT spp.*, ss.subscription_number, ss.customer_id, ss.scheme_id, 
                   sc.name AS scheme_name, c.name AS customer_name
            FROM scheme_subscription_payments spp
            INNER JOIN scheme_subscriptions ss ON ss.id = spp.subscription_id
            INNER JOIN schemes sc ON sc.id = ss.scheme_id
            INNER JOIN customers c ON c.id = ss.customer_id
            WHERE spp.amount_due > 0 AND spp.status IN ('pending','overdue','awaiting_verification')
            ORDER BY spp.status = 'overdue' DESC, spp.due_date ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Get pending payments by user ID
     */
    public static function getPendingByUserId(int $userId, int $limit = 300): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT spp.*, ss.scheme_id, ss.customer_id, ss.status AS subscription_status, ss.id AS subscription_id, ss.subscription_number,
                   sc.name AS scheme_name, sc.frequency, sc.amount AS scheme_amount,
                   c.name AS customer_name, c.phone AS customer_phone
            FROM scheme_subscription_payments spp
            INNER JOIN scheme_subscriptions ss ON ss.id = spp.subscription_id
            INNER JOIN customers c ON c.id = ss.customer_id
            INNER JOIN schemes sc ON sc.id = ss.scheme_id
            WHERE c.user_id = ?
              AND spp.amount_due > 0
              AND spp.status IN ('pending', 'overdue', 'awaiting_verification')
            ORDER BY
                CASE
                    WHEN spp.status = 'overdue' THEN 0
                    WHEN spp.status = 'awaiting_verification' THEN 1
                    ELSE 2
                END,
                spp.due_date ASC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get payments with filters for admin
     */
    public static function getAllWithFilters(?string $statusFilter = null, ?int $schemeFilter = null, ?int $subscriptionFilter = null, int $limit = 200, bool $onlyWithAmount = true): array {
        $conn = Database::getConnection();
        
        $params = [];
        $where = [];
        
        if ($onlyWithAmount) {
            $where[] = 'spp.amount_due > 0';
        }
        
        if ($statusFilter && $statusFilter !== 'all') {
            $where[] = 'spp.status = ?';
            $params[] = $statusFilter;
        }
        if ($schemeFilter && $schemeFilter > 0) {
            $where[] = 'ss.scheme_id = ?';
            $params[] = $schemeFilter;
        }
        if ($subscriptionFilter && $subscriptionFilter > 0) {
            $where[] = 'spp.subscription_id = ?';
            $params[] = $subscriptionFilter;
        }
        
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        
        $sql = "
            SELECT spp.*, ss.customer_id, ss.scheme_id, ss.subscription_number,
                   sc.name AS scheme_name,
                   c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone
            FROM scheme_subscription_payments spp
            INNER JOIN scheme_subscriptions ss ON ss.id = spp.subscription_id
            INNER JOIN schemes sc ON sc.id = ss.scheme_id
            INNER JOIN customers c ON c.id = ss.customer_id
            $whereSql
            ORDER BY spp.due_date ASC
            LIMIT ?
        ";
        
        $params[] = $limit;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get payment with customer and subscription details
     */
    public static function findByIdWithDetails(int $id): ?array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT spp.*, ss.customer_id, ss.scheme_id, ss.subscription_number,
                   sc.name AS scheme_name,
                   c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone
            FROM scheme_subscription_payments spp
            INNER JOIN scheme_subscriptions ss ON ss.id = spp.subscription_id
            INNER JOIN schemes sc ON sc.id = ss.scheme_id
            INNER JOIN customers c ON c.id = ss.customer_id
            WHERE spp.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row;
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['period_index'] = (int)($array['period_index'] ?? 0);
        $array['amount_due'] = $array['amount_due'] ? (float)$array['amount_due'] : null;
        return $array;
    }
}


