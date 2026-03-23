<?php

/**
 * User Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class User extends Model {
    protected string $table = 'users';
    
    public ?int $id = null;
    public ?string $google_id = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $name = null;
    public ?string $password = null;
    public ?string $picture_url = null;
    public ?bool $email_verified = null;
    public ?bool $is_verified = null;
    public ?bool $is_active = null;
    public ?string $role = null;
    public ?int $login_count = null;
    public ?string $last_login = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Find user by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find user by email
     */
    public static function findByEmail(string $email, ?string $role = null): ?self {
        $conn = Database::getConnection();
        $sql = "SELECT * FROM users WHERE email = ?";
        $params = [$email];
        
        if ($role !== null) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find user by email or name (username)
     */
    public static function findByEmailOrName(string $identifier, ?string $role = null): ?self {
        $conn = Database::getConnection();
        $sql = "SELECT * FROM users WHERE (email = ? OR name = ?)";
        $params = [$identifier, $identifier];
        
        if ($role !== null) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find user by Google ID
     */
    public static function findByGoogleId(string $googleId): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ?");
        $stmt->execute([$googleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find or create user by Google ID
     */
    public static function findOrCreateByGoogle(array $googleData): self {
        if (empty($googleData['google_id'])) {
            throw new \Exception('Google ID is required');
        }
        
        $conn = Database::getConnection();
        
        // First, try to find user by Google ID
        $user = self::findByGoogleId($googleData['google_id']);
        
        if ($user) {
            // Update existing user
            $updateData = [
                'name' => $googleData['name'] ?? $user->name,
                'email' => $googleData['email'] ?? $user->email,
                'picture_url' => $googleData['picture'] ?? $googleData['picture_url'] ?? $user->picture_url,
                'email_verified' => $googleData['email_verified'] ?? $user->email_verified,
                'last_login' => date('Y-m-d H:i:s'),
                'login_count' => ($user->login_count ?? 0) + 1
            ];
            $user->update($updateData);
            return $user;
        }
        
        // Try to find user by email and link Google account
        if (!empty($googleData['email'])) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$googleData['email']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $user = new self($row);
                // Link Google account to existing user
                $user->update([
                    'google_id' => $googleData['google_id'],
                    'name' => $googleData['name'] ?? $user->name,
                    'picture_url' => $googleData['picture'] ?? $googleData['picture_url'] ?? $user->picture_url,
                    'email_verified' => $googleData['email_verified'] ?? $user->email_verified,
                    'last_login' => date('Y-m-d H:i:s'),
                    'login_count' => ($user->login_count ?? 0) + 1
                ]);
                return $user;
            }
        }
        
        // Create new user
        $userData = [
            'google_id' => $googleData['google_id'],
            'email' => $googleData['email'] ?? '',
            'name' => $googleData['name'] ?? '',
            'picture_url' => $googleData['picture'] ?? $googleData['picture_url'] ?? null,
            'email_verified' => $googleData['email_verified'] ?? false,
            'is_verified' => true,
            'is_active' => true,
            'role' => 'customer',
            'login_count' => 1,
            'last_login' => date('Y-m-d H:i:s')
        ];
        
        return self::create($userData);
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin(): bool {
        return ($this->role ?? 'customer') === 'admin';
    }
    
    public function verifyPassword(string $password): bool {
        // If no password_hash property (which we removed), use the password property which mapped to the DB column
        if (isset($this->password)) {
            return password_verify($password, $this->password);
        }
        return false;
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['email_verified'] = (bool)($array['email_verified'] ?? false);
        $array['is_verified'] = (bool)($array['is_verified'] ?? false);
        $array['is_active'] = (bool)($array['is_active'] ?? true);
        $array['login_count'] = (int)($array['login_count'] ?? 0);
        // Don't expose password fields
        unset($array['password'], $array['password_hash']);
        return $array;
    }
}


