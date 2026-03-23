<?php

/**
 * Customer Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Customer extends Model {
    protected string $table = 'customers';
    
    public ?int $id = null;
    public ?int $user_id = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $address = null;
    public ?string $city = null;
    public ?string $state = null;
    public ?string $pincode = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Find customer by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find customer by email
     */
    public static function findByEmail(string $email): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find customer by phone
     */
    public static function findByPhone(string $phone): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM customers WHERE phone = ?");
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find customer by user_id
     */
    public static function findByUserId(int $userId): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM customers WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get customer statistics
     */
    public static function getStats(): array {
        $conn = Database::getConnection();
        
        $totalStmt = $conn->query("SELECT COUNT(*) FROM customers");
        $total = (int)$totalStmt->fetchColumn();
        
        $newThisMonthStmt = $conn->query("
            SELECT COUNT(*) FROM customers 
            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        $newThisMonth = (int)$newThisMonthStmt->fetchColumn();
        
        $repeatStmt = $conn->query("
            SELECT COUNT(DISTINCT customer_id) 
            FROM orders 
            GROUP BY customer_id 
            HAVING COUNT(*) > 1
        ");
        $repeatCustomers = (int)$repeatStmt->rowCount();
        
        return [
            'total' => $total,
            'new_this_month' => $newThisMonth,
            'repeat_customers' => $repeatCustomers
        ];
    }
    
    /**
     * Search customers
     */
    public static function search(string $searchTerm, int $limit = 50): array {
        $conn = Database::getConnection();
        $searchTerm = "%{$searchTerm}%";
        
        $stmt = $conn->prepare("
            SELECT * FROM customers 
            WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Get customers with order statistics
     */
    public static function getAllWithOrderStats(?string $search = null, int $limit = 50): array {
        $conn = Database::getConnection();
        
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $stmt = $conn->prepare("
                SELECT c.*, 
                       COUNT(o.id) as order_count,
                       COALESCE(SUM(o.total_amount), 0) as total_spent,
                       MAX(o.order_date) as last_order_date
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id
                WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?
                GROUP BY c.id
                ORDER BY c.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        } else {
            $stmt = $conn->prepare("
                SELECT c.*, 
                       COUNT(o.id) as order_count,
                       COALESCE(SUM(o.total_amount), 0) as total_spent,
                       MAX(o.order_date) as last_order_date
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id
                GROUP BY c.id
                ORDER BY c.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
        }
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            $customer = new self($row);
            $customerData = $customer->toArray();
            $customerData['order_count'] = (int)($row['order_count'] ?? 0);
            $customerData['total_spent'] = (float)($row['total_spent'] ?? 0);
            $customerData['last_order_date'] = $row['last_order_date'] ?? null;
            return $customerData;
        }, $results);
    }
    
    /**
     * Search customers with pagination and filters
     */
    public static function searchWithPagination(?string $search = null, int $page = 1, int $limit = 20, ?string $sortBy = null, ?string $sortOrder = null): array {
        $conn = Database::getConnection();
        $offset = ($page - 1) * $limit;
        
        // Build WHERE conditions
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.city LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countSql = "SELECT COUNT(DISTINCT c.id) FROM customers c WHERE {$whereClause}";
        $countParams = [];
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $countParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();
        
        // Validate and set sort parameters
        $allowedSortColumns = ['id', 'name', 'email', 'phone', 'city', 'created_at', 'updated_at'];
        $sortColumn = 'id'; // Default
        $sortDirection = 'DESC'; // Default
        
        if ($sortBy && in_array($sortBy, $allowedSortColumns)) {
            $sortColumn = $sortBy;
        }
        
        if ($sortOrder && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $sortDirection = strtoupper($sortOrder);
        }
        
        // Get customers with order stats
        $sql = "SELECT c.*, 
                       COUNT(o.id) as order_count,
                       COALESCE(SUM(o.total_amount), 0) as total_spent,
                       MAX(o.order_date) as last_order_date
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id
                WHERE {$whereClause}
                GROUP BY c.id
                ORDER BY c.{$sortColumn} {$sortDirection}
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $customers = array_map(function($row) {
            $customer = new self($row);
            $customerData = $customer->toArray();
            $customerData['order_count'] = (int)($row['order_count'] ?? 0);
            $customerData['total_spent'] = (float)($row['total_spent'] ?? 0);
            $customerData['last_order_date'] = $row['last_order_date'] ?? null;
            $customerData['balance'] = 0; // Default balance, can be calculated from other sources if needed
            return $customerData;
        }, $results);
        
        return [
            'customers' => $customers,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get customers with subscription statistics
     */
    public static function getAllWithSubscriptionStats(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("
            SELECT c.id, c.name, c.email, c.phone, c.city,
                   COUNT(DISTINCT ss.id) AS subscription_count,
                   COALESCE(SUM(CASE WHEN spp.status = 'paid' THEN spp.amount_due ELSE 0 END), 0) AS total_paid,
                   COALESCE(SUM(CASE WHEN spp.status IN ('pending','overdue','awaiting_verification') THEN spp.amount_due ELSE 0 END), 0) AS total_outstanding,
                   MAX(ss.created_at) AS last_subscription_at
            FROM customers c
            INNER JOIN scheme_subscriptions ss ON ss.customer_id = c.id
            LEFT JOIN scheme_subscription_payments spp ON spp.subscription_id = ss.id
            GROUP BY c.id
            ORDER BY total_outstanding DESC, c.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get customer report with order statistics
     */
    public static function getCustomerReport(int $limit = 50): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("
            SELECT 
                c.id,
                c.name,
                c.email,
                c.phone,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_spent,
                MAX(o.order_date) as last_order_date
            FROM customers c
            LEFT JOIN orders o ON c.id = o.customer_id
            GROUP BY c.id
            ORDER BY total_spent DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find or create customer
     * For POS: Creates a NEW customer record for each order (for shipping purposes)
     * If user exists in users table (by email or phone), sets user_id on the new customer
     */
    public static function findOrCreate(array $data): self {
        $conn = Database::getConnection();
        
        // Parse city and pincode from address
        $city = '';
        $pincode = '';
        if (!empty($data['address'])) {
            if (preg_match('/(.+?)\s*[-,\s]+\s*(\d{6})/i', $data['address'], $m)) {
                $city = trim($m[1]);
                $pincode = trim($m[2]);
            }
        }
        $data['city'] = $city;
        $data['pincode'] = $pincode;
        
        $email = !empty($data['email']) ? trim($data['email']) : '';
        $phone = !empty($data['phone']) ? trim($data['phone']) : '';
        
        // Check if user exists in users table by email or phone
        $userId = null;
        if (!empty($email) && strpos($email, '@') !== false) {
            $user = \App\Models\User::findByEmail($email);
            if ($user) {
                $userId = $user->id;
            }
        }
        
        // If no user found by email, try phone
        if (!$userId && !empty($phone)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
            $stmt->execute([$phone]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userRow) {
                $userId = (int)$userRow['id'];
            }
        }
        
        // Set user_id if found
        if ($userId) {
            $data['user_id'] = $userId;
        }
        
        // For POS: Always create a NEW customer record per order (for shipping/delivery purposes)
        // Use the original email as-is (unique constraint has been removed from DB)
        if (empty($email) || strpos($email, '@') === false) {
            // Generate an email if not provided
            $email = 'pos-' . time() . '-' . mt_rand(1000, 9999) . '@suncrackers.in';
        }
        
        $data['email'] = $email;
        
        // Create new customer record with order details (can have duplicate emails now)
        return self::create($data);
    }
    
    /**
     * Ensure unique customer email
     * Uses timestamp-based suffix for better readability (e.g., email+1704123456@domain.com)
     */
    private static function ensureUniqueEmail(string $baseEmail): string {
        $baseEmail = strtolower(trim($baseEmail));
        list($local, $domain) = explode('@', $baseEmail, 2);
        // Remove any existing + suffix
        $local = preg_replace('/\+.*$/', '', $local);
        $domain = trim($domain) !== '' ? $domain : 'suncrackers.in';
        
        // Try original email first
        $candidate = $local . '@' . $domain;
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) FROM customers WHERE email = ?");
        $stmt->execute([$candidate]);
        $exists = (int)$stmt->fetchColumn();
        
        if ($exists === 0) {
            return $candidate;
        }
        
        // Generate unique email with timestamp suffix
        // Format: email+timestamp@domain.com (e.g., user@example.com+1704123456@example.com)
        $timestamp = time();
        $candidate = $local . '+' . $timestamp . '@' . $domain;
        
        // Check if timestamp version exists (unlikely but possible)
        $stmt->execute([$candidate]);
        $exists = (int)$stmt->fetchColumn();
        if ($exists === 0) {
            return $candidate;
        }
        
        // If timestamp collision (very unlikely), add random suffix
        $candidate = $local . '+' . $timestamp . '-' . mt_rand(1000, 9999) . '@' . $domain;
        return $candidate;
    }
}

