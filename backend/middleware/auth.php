<?php
/**
 * AgriConnect - Authentication Middleware
 * Session-based authentication with token support
 */

require_once __DIR__ . '/../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authenticate the current request.
 * Returns the authenticated user or sends 401 response.
 */
function authenticate() {
    // Check session first
    if (isset($_SESSION['user_id'])) {
        $db = getDB();
        $stmt = $db->prepare("SELECT u.*, eo.id as extension_officer_id FROM users u LEFT JOIN extension_officers eo ON eo.user_id = u.id WHERE u.id = ? AND u.status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            unset($user['password_hash']);
            return $user;
        }
    }

    // Check Authorization header token
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');

    if (!empty($authHeader) && strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
        $db = getDB();

        // Simple token validation (token stored as hash in session_tokens table concept)
        // For this system, we use a simple approach: token = base64(user_id:timestamp:secret)
        $decoded = base64_decode($token);
        if ($decoded) {
            $parts = explode(':', $decoded);
            if (count($parts) === 3) {
                $userId = $parts[0];
                $stmt = $db->prepare("SELECT u.*, eo.id as extension_officer_id FROM users u LEFT JOIN extension_officers eo ON eo.user_id = u.id WHERE u.id = ? AND u.status = 'active'");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if ($user) {
                    unset($user['password_hash']);
                    return $user;
                }
            }
        }
    }

    // No valid authentication found
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required. Please login.']);
    exit;
}

/**
 * Check if user has a specific role
 */
function requireRole($roles) {
    $user = authenticate();

    if (is_string($roles)) {
        $roles = [$roles];
    }

    if (!in_array($user['role'], $roles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied. Required role: ' . implode(' or ', $roles)]);
        exit;
    }

    return $user;
}

/**
 * Generate an auth token for a user
 */
function generateToken($userId) {
    $secret = 'agriconnect_ss_2026_secret';
    $timestamp = time();
    $payload = $userId . ':' . $timestamp . ':' . $secret;
    return base64_encode($payload);
}

/**
 * Get JSON request body
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Set CORS headers for API
 */
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
?>
