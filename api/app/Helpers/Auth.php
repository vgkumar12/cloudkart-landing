<?php

namespace App\Helpers;

/**
 * Auth - HMAC-based token helper.
 *
 * Token format: {base64url_payload}.{hex_hmac}
 * Payload JSON: { uid, role, exp }
 *
 * Entirely server-side — role is baked into the signed token,
 * so the client cannot tamper with it.
 */
class Auth {

    private static function secret(): string {
        if (!defined('APP_SECRET') || empty(APP_SECRET)) {
            throw new \RuntimeException('APP_SECRET is not defined in config.php');
        }
        return APP_SECRET;
    }

    // -------------------------------------------------------------------------

    public static function generateToken(int $userId, string $role, int $ttlSeconds = 86400): string {
        $payload = base64_encode(json_encode([
            'uid'  => $userId,
            'role' => $role,
            'exp'  => time() + $ttlSeconds,
            'iat'  => time(),
        ]));

        $sig = hash_hmac('sha256', $payload, self::secret());
        return $payload . '.' . $sig;
    }

    /**
     * Verify token from Authorization header.
     * Returns decoded payload array on success, null on failure.
     */
    public static function verifyToken(?string $token): ?array {
        if (!$token) return null;

        // Strip "Bearer " prefix if present
        if (stripos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;

        [$payload, $sig] = $parts;

        // Constant-time comparison
        $expected = hash_hmac('sha256', $payload, self::secret());
        if (!hash_equals($expected, $sig)) return null;

        $data = json_decode(base64_decode($payload), true);
        if (!$data || empty($data['exp']) || $data['exp'] < time()) return null;

        return $data;
    }

    /**
     * Extract token from the current request's Authorization header.
     */
    public static function tokenFromRequest(): ?string {
        $header = $_SERVER['HTTP_AUTHORIZATION']
               ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
               ?? getallheaders()['Authorization']
               ?? null;
        return $header ?: null;
    }

    /**
     * Require a valid super-admin token. Aborts with 401/403 if invalid.
     * Returns the payload on success.
     */
    public static function requireSuperAdmin(): array {
        $token   = self::tokenFromRequest();
        $payload = self::verifyToken($token);

        if (!$payload) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        }

        if (($payload['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Super admin access required']);
            exit;
        }

        return $payload;
    }
}
