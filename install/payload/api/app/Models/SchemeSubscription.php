<?php

/**
 * Scheme Subscription Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class SchemeSubscription extends Model {
    protected string $table = 'scheme_subscriptions';
    
    public ?int $id = null;
    public ?int $scheme_id = null;
    public ?int $customer_id = null;
    public ?string $subscription_number = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $total_periods = null;
    public ?float $amount_per_period = null;
    public ?string $status = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Generate subscription number
     */
    public static function generateSubscriptionNumber(int $subscriptionId, int $schemeId): string {
        return sprintf('SC%03d-%05d', $schemeId, $subscriptionId);
    }
    
    /**
     * Get subscriptions by scheme ID
     */
    public static function getBySchemeId(int $schemeId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM scheme_subscriptions WHERE scheme_id = ? ORDER BY created_at DESC");
        $stmt->execute([$schemeId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Get subscriptions by customer ID
     */
    public static function getByCustomerId(int $customerId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT ss.*, s.name as scheme_name, s.frequency as scheme_frequency
                                FROM scheme_subscriptions ss
                                LEFT JOIN schemes s ON ss.scheme_id = s.id
                                WHERE ss.customer_id = ? ORDER BY ss.created_at DESC");
        $stmt->execute([$customerId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Find subscription by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT ss.*, s.name as scheme_name, s.frequency as scheme_frequency
                                FROM scheme_subscriptions ss
                                LEFT JOIN schemes s ON ss.scheme_id = s.id
                                WHERE ss.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get subscriptions by user ID
     */
    public static function getByUserId(int $userId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT 
                ss.*,
                s.name AS scheme_name,
                s.description,
                s.frequency,
                s.amount,
                s.duration_months,
                s.bonus_months,
                s.start_month,
                c.name as customer_name,
                c.email as customer_email,
                c.phone as customer_phone
            FROM scheme_subscriptions ss
            JOIN schemes s ON ss.scheme_id = s.id
            JOIN customers c ON ss.customer_id = c.id
            WHERE c.user_id = ?
            ORDER BY ss.created_at DESC
        ");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Create subscription with payment schedule
     */
    public static function createWithPaymentSchedule(array $subscriptionData, array $schemeData, ?int $userId = null): self {
        $conn = Database::getConnection();
        $conn->beginTransaction();
        
        try {
            // Create subscription
            $startDate = date('Y-m-d');
            $durationMonths = (int)$schemeData['duration_months'];
            $bonusMonths = (int)$schemeData['bonus_months'];
            $totalPeriods = $durationMonths + $bonusMonths;
            $amountPer = (float)$schemeData['amount'];
            
            $subscription = self::create([
                'scheme_id' => $subscriptionData['scheme_id'],
                'customer_id' => $subscriptionData['customer_id'],
                'subscription_number' => null, // Will be set after creation
                'start_date' => $startDate,
                'total_periods' => $totalPeriods,
                'amount_per_period' => $amountPer,
                'status' => 'active'
            ]);
            
            // Generate subscription number
            $subscriptionNumber = self::generateSubscriptionNumber($subscription->id, $subscriptionData['scheme_id']);
            $subscription->update(['subscription_number' => $subscriptionNumber]);
            
            // Generate payment schedule
            $frequency = $schemeData['frequency'] === 'weekly' ? 'weekly' : 'monthly';
            $startMonth = !empty($schemeData['start_month']) 
                ? new \DateTime($schemeData['start_month']) 
                : new \DateTime(date('Y-m-01'));
            
            if ($frequency === 'monthly') {
                $due = clone $startMonth;
                for ($i = 1; $i <= $totalPeriods; $i++) {
                    $dueDay = (clone $due)->setDate((int)$due->format('Y'), (int)$due->format('m'), 10);
                    $amt = ($i <= $durationMonths) ? $amountPer : 0.00;
                    $status = ($amt > 0 && $dueDay < new \DateTime('today')) ? 'overdue' : 'pending';
                    
                    SchemeSubscriptionPayment::create([
                        'subscription_id' => $subscription->id,
                        'period_index' => $i,
                        'due_date' => $dueDay->format('Y-m-d'),
                        'amount_due' => $amt,
                        'status' => $status
                    ]);
                    
                    $due->modify('+1 month');
                }
            } else { // weekly
                $due = clone $startMonth;
                for ($i = 1; $i <= $totalPeriods; $i++) {
                    $amt = ($i <= $durationMonths) ? $amountPer : 0.00;
                    $status = ($amt > 0 && $due < new \DateTime('today')) ? 'overdue' : 'pending';
                    
                    SchemeSubscriptionPayment::create([
                        'subscription_id' => $subscription->id,
                        'period_index' => $i,
                        'due_date' => $due->format('Y-m-d'),
                        'amount_due' => $amt,
                        'status' => $status
                    ]);
                    
                    $due->modify('+7 days');
                }
            }
            
            $conn->commit();
            return $subscription;
        } catch (\Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * Get payments for this subscription
     */
    public function getPayments(): array {
        if (!$this->id) {
            return [];
        }
        
        return SchemeSubscriptionPayment::getBySubscriptionId($this->id);
    }
    
    /**
     * Get subscription statistics
     */
    public static function getStats(): array {
        $conn = Database::getConnection();
        
        $activeStmt = $conn->query("SELECT COUNT(*) FROM scheme_subscriptions WHERE status = 'active'");
        $active = (int)$activeStmt->fetchColumn();
        
        return [
            'active' => $active
        ];
    }
    
    /**
     * Get subscriptions with payment statistics and filters
     */
    public static function getAllWithPaymentStats(?string $statusFilter = null, ?int $schemeFilter = null, int $limit = 200): array {
        $conn = Database::getConnection();
        
        $params = [];
        $where = [];
        
        if ($statusFilter && $statusFilter !== 'all') {
            $where[] = 'ss.status = ?';
            $params[] = $statusFilter;
        }
        if ($schemeFilter && $schemeFilter > 0) {
            $where[] = 'ss.scheme_id = ?';
            $params[] = $schemeFilter;
        }
        
        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }
        
        $sql = "
            SELECT ss.*, sc.name AS scheme_name, sc.frequency, sc.amount,
                   c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                   COALESCE(SUM(CASE WHEN spp.status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                   COALESCE(SUM(CASE WHEN spp.status IN ('pending','overdue','awaiting_verification') THEN 1 ELSE 0 END), 0) AS pending_count,
                   COALESCE(SUM(CASE WHEN spp.status = 'paid' THEN spp.amount_due ELSE 0 END), 0) AS paid_amount,
                   COALESCE(SUM(CASE WHEN spp.status IN ('pending','overdue','awaiting_verification') THEN spp.amount_due ELSE 0 END), 0) AS outstanding_amount
            FROM scheme_subscriptions ss
            INNER JOIN schemes sc ON sc.id = ss.scheme_id
            INNER JOIN customers c ON c.id = ss.customer_id
            LEFT JOIN scheme_subscription_payments spp ON spp.subscription_id = ss.id
            $whereSql
            GROUP BY ss.id
            ORDER BY ss.created_at DESC
            LIMIT ?
        ";
        
        $params[] = $limit;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['total_periods'] = (int)($array['total_periods'] ?? 0);
        $array['amount_per_period'] = $array['amount_per_period'] ? (float)$array['amount_per_period'] : null;
        
        // Include payments if subscription is loaded
        if ($this->id) {
            $array['payments'] = $this->getPayments();
        }
        
        return $array;
    }
}


