<?php

/**
 * Authentication Controller
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Validator;
use App\Helpers\GoogleAuth;
use App\Helpers\JwtHelper;
use App\Helpers\SettingsHelper;
use App\Helpers\SessionHelper;
use App\Models\User;
use App\Models\Customer;
use PDO;

class AuthController extends Controller {
    
    /**
     * Login (Google OAuth callback or session-based)
     * POST /api/auth/login
     */
    public function login(): void {
        try {
            // Determine context based on request path (admin vs frontend)
            $context = SessionHelper::getContext($this->request->path());
            SessionHelper::startSession($context);
            
            $data = $this->request->all();
            
            // Check if Google OAuth token provided
            if (!empty($data['google_token'])) {
                // Verify Google token (simplified - in production, verify with Google API)
                $this->error('Google OAuth not implemented yet', 501);
                return;
            }
            
            // Email/Username + Password login (for admin and test login)
            if (!empty($data['email']) || !empty($data['username'])) {
                $identifier = $data['email'] ?? $data['username'];
                $password = $data['password'] ?? '';
                
                if (empty($password)) {
                    $this->error('Password is required', 400);
                    return;
                }
                
                // Check for test login (development mode)
                $testLoginEnabled = SettingsHelper::get('test_login_enabled', defined('TEST_LOGIN_ENABLED') ? TEST_LOGIN_ENABLED : false);
                $dummyPassword = SettingsHelper::get('dummy_password', defined('DUMMY_PASSWORD') ? DUMMY_PASSWORD : 'test123');
                
                if ($testLoginEnabled && $password === $dummyPassword) {
                    // Test login: Find or create user with the email
                    // CRITICAL: Limit search to context-appropriate role
                    $expectedRole = ($context === 'admin') ? 'admin' : null; // customers can have null or 'customer' role
                    $user = User::findByEmailOrName($identifier, $expectedRole);
                    
                    if (!$user) {
                        // Check if customer exists first
                        $customer = Customer::findByEmail($identifier);
                        
                        // Create user for test login
                        $userData = [
                            'email' => $identifier,
                            'name' => $customer->name ?? 'Test User',
                            'role' => 'customer',
                            'password' => password_hash($dummyPassword, PASSWORD_DEFAULT),
                            'is_active' => true,
                            'login_count' => 0,
                            'last_login' => date('Y-m-d H:i:s')
                        ];
                        
                        try {
                            $user = User::create($userData);
                            
                            // Link customer to user if customer exists
                            if ($customer && $customer->user_id === null) {
                                $customer->update(['user_id' => $user->id]);
                            } elseif (!$customer) {
                                // Create customer linked to user
                                try {
                                    Customer::create([
                                        'user_id' => $user->id,
                                        'email' => $identifier,
                                        'name' => 'Test User'
                                    ]);
                                } catch (\Exception $e) {
                                    // Customer creation is optional - log and continue
                                    error_log('Failed to create customer for test login: ' . $e->getMessage());
                                }
                            }
                        } catch (\Exception $e) {
                            error_log('Failed to create user for test login: ' . $e->getMessage());
                            $this->error('Failed to create test user account', 500);
                            return;
                        }
                    }
                    
                    // Verify user is active
                    if (!$user || !$user->is_active) {
                        $this->error('Account is inactive. Please contact administrator.', 403);
                        return;
                    }
                    
                    // CRITICAL: Verify we're in the correct session context before setting user data
                    // Session was already started in startSession() call above, but verify it's correct
                    $currentSessionName = session_name();
                    $expectedSessionName = SessionHelper::getSessionName($context);
                    
                    if ($currentSessionName !== $expectedSessionName) {
                        // Wrong session detected - this should not happen, but handle it
                        error_log("WARNING: Session name mismatch during login. Expected: {$expectedSessionName}, Got: {$currentSessionName}. Forcing correct session.");
                        SessionHelper::forceCorrectSession($context);
                        
                        // Re-verify after forcing
                        $currentSessionName = session_name();
                        if ($currentSessionName !== $expectedSessionName) {
                            error_log("CRITICAL: Cannot establish correct session context! Expected: {$expectedSessionName}, Got: {$currentSessionName}");
                            $this->error('Session initialization failed', 500);
                            return;
                        }
                    }
                    
                    // Finalize login (set session, migrate cart, etc.)
                    $this->finalizeLogin($user, $data);
                    return;
                }
                
                // Normal password login
                // ...
                // Find user by email or name (username)
                $expectedRole = ($context === 'admin') ? 'admin' : null;
                $user = User::findByEmailOrName($identifier, $expectedRole);
                
                if (!$user) {
                    $this->error('Invalid email/username or password for ' . $context . ' account', 401);
                    return;
                }
                
                if (!$user->is_active) {
                    $this->error('Account is inactive. Please contact administrator.', 403);
                    return;
                }
                
                // Verify password
                $passwordVerified = $user->verifyPassword($password);
                
                if (!$passwordVerified) {
                    error_log("Login attempt failed for user ID {$user->id} ({$user->email}): Password verification failed");
                    $this->error('Invalid email/username or password', 401);
                    return;
                }
                
                // CRITICAL: Verify we're using the correct session context before setting data
                if (!SessionHelper::verifySessionContext($context)) {
                    SessionHelper::forceCorrectSession($context);
                }
                
                // Double-check: ensure we're in the correct session
                $currentSessionName = session_name();
                $expectedSessionName = SessionHelper::getSessionName($context);
                if ($currentSessionName !== $expectedSessionName) {
                    SessionHelper::forceCorrectSession($context);
                }
                
                // Finalize login
                $this->finalizeLogin($user, $data);
                return;
            }
            
            $this->unauthorized('Invalid credentials');
        } catch (\Exception $e) {
            $this->error('Login failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Logout
     * POST /api/auth/logout
     */
    public function logout(): void {
        // Ensure correct session is started based on context
        $context = SessionHelper::getContext($this->request->path());
        SessionHelper::startSession($context);
        
        session_destroy();
        
        $this->success(null, 'Logout successful');
    }
    
    /**
     * Get current user
     * GET /api/auth/me
     */
    public function me(): void {
        // Ensure correct session is started based on context
        $context = SessionHelper::getContext($this->request->path());
        SessionHelper::startSession($context);
        
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            $this->unauthorized('Not authenticated');
            return;
        }
        
        // Refresh session cookie expiration on each authenticated request
        SessionHelper::refreshSessionCookie();
        
        try {
            // Use Model method
            $user = User::findById($userId);
            
            if (!$user || !$user->is_active) {
                $this->unauthorized('User not found');
                return;
            }
            
            $this->success($user->toArray(), 'User retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve user: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Send OTP
     * POST /api/auth/send-otp
     */
    public function sendOtp(): void {
        try {
            $context = SessionHelper::getContext($this->request->path());
            SessionHelper::startSession($context);
            
            $data = $this->request->all();
            $phone = trim($data['phone'] ?? '');
            
            if (empty($phone)) {
                $this->validationError(['phone' => ['Phone is required']], 'Validation failed');
                return;
            }
            
            // Generate OTP (6 digits)
            $otp = (string)rand(100000, 999999);
            
            // Store in session
            $_SESSION['auth_otp'] = $otp;
            $_SESSION['auth_otp_phone'] = $phone;
            $_SESSION['auth_otp_expires'] = time() + 300; // 5 minutes
            
            // Send via WhatsApp
            $waService = new \App\Services\WhatsAppService();
            $result = $waService->sendOtp($phone, $otp);
            
            if ($result['success']) {
                $this->success(null, 'OTP sent successfully');
            } else {
                $msg = $result['message'] ?? 'Unknown error';
                $this->error('Failed to send OTP: ' . $msg, 500);
            }
        } catch (\Exception $e) {
            $this->error('Error sending OTP: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Verify OTP
     * POST /api/auth/verify-otp
     */
    public function verifyOtp(): void {
        try {
            // Ensure correct session is started based on context
            $context = SessionHelper::getContext($this->request->path());
            SessionHelper::startSession($context);
            
            $data = $this->request->all();
            $otp = trim($data['otp'] ?? '');
            $phone = trim($data['phone'] ?? '');
            
            if (empty($otp) || empty($phone)) {
                $this->validationError([
                    'otp' => ['OTP is required'],
                    'phone' => ['Phone is required']
                ], 'Validation failed');
                return;
            }
            
            // Verify OTP
            $sessionOtp = $_SESSION['auth_otp'] ?? null;
            $sessionPhone = $_SESSION['auth_otp_phone'] ?? null;
            $expires = $_SESSION['auth_otp_expires'] ?? 0;
            
            if (!$sessionOtp || $sessionPhone !== $phone) {
                 $this->validationError(['otp' => ['Invalid OTP request. Please request a new OTP.']], 'Validation failed');
                 return;
            }
            
            if (time() > $expires) {
                 $this->validationError(['otp' => ['OTP has expired']], 'Validation failed');
                 return;
            }
            
            if ($otp !== $sessionOtp) {
                 $this->validationError(['otp' => ['Invalid OTP']], 'Validation failed');
                 return;
            }
            
            // Clear OTP
            unset($_SESSION['auth_otp']);
            unset($_SESSION['auth_otp_phone']);
            unset($_SESSION['auth_otp_expires']);
            
            // Use Model method to find or create customer
            $customer = Customer::findByPhone($phone);
            
            if (!$customer) {
                // Create customer using Model
                $customer = Customer::create(['phone' => $phone, 'name' => 'Customer ' . substr($phone, -4)]);
            }
            
            $customerId = $customer->id;
            
            // Set session
            $_SESSION['user_id'] = $customerId;
            $_SESSION['customer_id'] = $customerId;
            
            $this->success([
                'customer_id' => $customerId,
                'session_id' => session_id()
            ], 'OTP verified successfully');
        } catch (\Exception $e) {
            $this->error('Failed to verify OTP: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Google OAuth callback
     * POST /api/auth/google-callback
     */
    public function googleCallback(): void {
        try {
            // Ensure correct session is started based on context (should be frontend for Google login)
            $context = SessionHelper::getContext($this->request->path());
            SessionHelper::startSession($context);
            
            // Capture old session id to migrate cart
            $oldSessionId = session_id();
            
            $data = $this->request->all();
            $googleToken = $data['token'] ?? $data['credential'] ?? '';
            
            if (empty($googleToken)) {
                $this->validationError(['token' => ['Google token is required']], 'Validation failed');
                return;
            }
            
            // Verify Google token
            $googleData = GoogleAuth::verifyToken($googleToken);
            
            if (!$googleData) {
                $this->error('Invalid Google token', 401);
                return;
            }
            
            // Get or create user from Google data
            $user = User::findOrCreateByGoogle($googleData);
            
            if (!$user) {
                $this->error('Failed to create/retrieve user', 500);
                return;
            }
            
            // Preserve cart across session hardening
            $preserveKeys = ['cart', 'cart_items', 'cart_id'];
            $preserved = [];
            foreach ($preserveKeys as $k) {
                if (isset($_SESSION[$k])) {
                    $preserved[$k] = $_SESSION[$k];
                }
            }
            
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            
            // Restore preserved values
            foreach ($preserved as $k => $v) {
                $_SESSION[$k] = $v;
            }
            
            // Set session
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_email'] = $user->email;
            $_SESSION['user_role'] = $user->role ?? 'customer';
            $_SESSION['customer_id'] = $user->id; // For compatibility
            
            // Migrate cart from old session to new logged-in session
            try {
                $conn = Database::getConnection();
                $newSessionId = session_id();
                $userId = $user->id;
                
                if (!empty($oldSessionId) && $oldSessionId !== $newSessionId) {
                    // Move cart items to new session and attach user
                    $stmt = $conn->prepare("UPDATE cart SET session_id = ?, user_id = ? WHERE session_id = ? AND user_id IS NULL");
                    $stmt->execute([$newSessionId, $userId, $oldSessionId]);
                    
                    // Update cart_sessions snapshot if present
                    $stmt2 = $conn->prepare("UPDATE cart_sessions SET session_id = ?, user_id = ? WHERE session_id = ? AND (user_id IS NULL OR user_id = 0)");
                    $stmt2->execute([$newSessionId, $userId, $oldSessionId]);
                }
            } catch (\Exception $cartEx) {
                error_log('Cart migration after Google login failed: ' . $cartEx->getMessage());
            }
            
            // Update last login
            $user->update([
                'last_login' => date('Y-m-d H:i:s'),
                'login_count' => ($user->login_count ?? 0) + 1
            ]);
            
            $this->success([
                'user' => $user->toArray(),
                'session_id' => session_id()
            ], 'Google login successful');
        } catch (\Exception $e) {
            $this->error('Failed to process Google OAuth: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate JWT token
     * POST /api/token
     * Public endpoint - generates token for frontend
     */
    public function generateToken(): void {
        try {
            // Generate a simple API token for frontend
            // In production, you might want to validate client credentials
            $payload = [
                'type' => 'api',
                'client' => 'frontend',
                'issued_at' => time()
            ];
            
            $token = JwtHelper::generate($payload, defined('JWT_EXPIRATION') ? JWT_EXPIRATION : 3600);
            // Extract token ID from generated token for refresh token
            $decoded = JwtHelper::decode($token);
            $tokenId = $decoded['jti'] ?? bin2hex(random_bytes(16));
            $refreshToken = JwtHelper::generateRefreshToken($tokenId);
            
            $this->success([
                'token' => $token,
                'refresh_token' => $refreshToken,
                'expires_in' => defined('JWT_EXPIRATION') ? JWT_EXPIRATION : 3600,
                'token_type' => 'Bearer'
            ], 'Token generated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to generate token: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Refresh JWT token
     * POST /api/token/refresh
     */
    public function refreshToken(): void {
        try {
            $data = $this->request->all();
            $refreshToken = $data['refresh_token'] ?? '';
            
            if (empty($refreshToken)) {
                $this->validationError(['refresh_token' => ['Refresh token is required']], 'Validation failed');
                return;
            }
            
            // Validate refresh token
            $payload = JwtHelper::validate($refreshToken);
            
            if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
                $this->unauthorized('Invalid refresh token');
                return;
            }
            
            // Generate new access token
            $newPayload = [
                'type' => 'api',
                'client' => 'frontend',
                'issued_at' => time()
            ];
            
            $token = JwtHelper::generate($newPayload, defined('JWT_EXPIRATION') ? JWT_EXPIRATION : 3600);
            
            $this->success([
                'token' => $token,
                'expires_in' => defined('JWT_EXPIRATION') ? JWT_EXPIRATION : 3600,
                'token_type' => 'Bearer'
            ], 'Token refreshed successfully');
        } catch (\Exception $e) {
            $this->error('Failed to refresh token: ' . $e->getMessage(), 500);
        }
    }
    /**
     * Finalize login process: set session, migrate cart, refresh cookie
     * @param object $user User model instance
     * @param array $data Request data (for session_id)
     */
    private function finalizeLogin($user, array $data): void {
        // Set session data
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_role'] = $user->role ?? 'customer';
        $_SESSION['is_logged_in'] = true;
        
        // Settings for customer context
        $_SESSION['customer_id'] = $user->id; 
        
        // Capture old session ID before regeneration for cart migration
        // Strategy: Try migrating BOTH the session ID sent from frontend AND the current PHP session ID
        $frontendSessionId = $data['session_id'] ?? null;
        $cookieSessionId = session_id();
        
        // Regenerate session ID for security (preserves $_SESSION data)
        session_regenerate_id(true);
        
        // Migrate guest cart to user using Frontend Session ID
        $totalMigrated = 0;
        $debugIds = [];
        
        if ($frontendSessionId) {
             $debugIds[] = substr($frontendSessionId, 0, 8) . '...';
             $totalMigrated += \App\Models\Cart::migrateGuestCartToUser($frontendSessionId, $user->id);
        }
        
        // Migrate guest cart to user using Cookie Session ID (if different)
        if ($cookieSessionId && $cookieSessionId !== $frontendSessionId) {
             $debugIds[] = substr($cookieSessionId, 0, 8) . '...';
             $totalMigrated += \App\Models\Cart::migrateGuestCartToUser($cookieSessionId, $user->id);
        }
        
        // Refresh session cookie with new session ID and correct path
        SessionHelper::refreshSessionCookie();
        
        // Log cookie status for debug
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            $formattedHeaders = implode(' | ', headers_list());
            if (strpos($formattedHeaders, 'Set-Cookie') === false) {
                 error_log("AuthController: ⚠️ WARNING - Cookie header MISSING in finalizeLogin.");
            }
        }
        
        $debugMsg = " (Migrated: $totalMigrated items. Checked: " . implode(', ', $debugIds) . ")";
        
        $this->success([
            'user' => $user->toArray(),
            'session_id' => session_id(),
            'session_name' => session_name(),
            'migrated_count' => $totalMigrated,
            'debug_ids' => $debugIds
        ], 'Login successful' . (defined('ENVIRONMENT') && ENVIRONMENT === 'development' ? $debugMsg : ''));
    }
}

