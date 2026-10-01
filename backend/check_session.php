<?php
/**
 * AgriConnect - Session Check & Page Helpers
 * Include this at the top of PHP pages for server-side auth.
 * Usage: require_once __DIR__ . '/../../backend/check_session.php';
 */

require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require authentication - redirects to login if not logged in.
 * Returns the full user row from DB.
 */
function requireLogin($allowedRoles = []) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /Agriconnect/frontend/login.php');
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, eo.id as extension_officer_id
        FROM users u
        LEFT JOIN extension_officers eo ON eo.user_id = u.id
        WHERE u.id = ? AND u.status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: /Agriconnect/frontend/login.php');
        exit;
    }

    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles)) {
        header('Location: ' . getDashboardUrl($user['role']));
        exit;
    }

    unset($user['password_hash']);
    return $user;
}

/**
 * Get dashboard URL for a role
 */
function getDashboardUrl($role) {
    $urls = [
        'admin' => '/Agriconnect/frontend/admin/dashboard.php',
        'extension_officer' => '/Agriconnect/frontend/extension/dashboard.php',
        'farmer' => '/Agriconnect/frontend/farmer/dashboard.php',
    ];
    // Retired/unsupported roles (e.g. cooperative_leader) are sent to logout to clear the session
    return $urls[$role] ?? '/Agriconnect/frontend/logout.php';
}

/**
 * Get the base path prefix for asset references.
 * Adjust based on page depth.
 */
function basePath($depth = 1) {
    return str_repeat('../', $depth);
}

/**
 * Safe escape for HTML output
 */
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format SSP amount
 */
function formatSSP($amount) {
    return number_format((float)$amount, 0, '.', ',');
}

/**
 * Format date for display
 */
function formatDate($dateStr) {
    if (!$dateStr) return '';
    return date('d M Y', strtotime($dateStr));
}
?>
