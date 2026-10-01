<?php
/**
 * AgriConnect - Admin Dashboard API
 * GET /api/admin/dashboard.php
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$user = requireRole('admin');
$db = getDB();

$stats = [];

// Count extension officers
$stmt = $db->query("SELECT COUNT(*) as count FROM extension_officers");
$stats['extension_officers'] = (int)$stmt->fetch()['count'];

// Count farmers
$stmt = $db->query("SELECT COUNT(*) as count FROM farmers");
$stats['farmers'] = (int)$stmt->fetch()['count'];

// Count cooperatives
$stmt = $db->query("SELECT COUNT(*) as count FROM cooperatives WHERE status = 'active'");
$stats['cooperatives'] = (int)$stmt->fetch()['count'];

// Count active opportunities
$stmt = $db->query("SELECT COUNT(*) as count FROM opportunities WHERE status = 'active'");
$stats['opportunities'] = (int)$stmt->fetch()['count'];

// Recent extension officers
$stmt = $db->query("
    SELECT u.id, u.name, u.email, u.status, l.name as location_name
    FROM users u
    JOIN extension_officers eo ON eo.user_id = u.id
    LEFT JOIN locations l ON l.id = eo.location_id
    ORDER BY u.created_at DESC
    LIMIT 5
");
$recentOfficers = $stmt->fetchAll();

// Pending success stories
$stmt = $db->query("
    SELECT id, title, farmer_name, created_at
    FROM success_stories
    WHERE status = 'pending'
    ORDER BY created_at DESC
    LIMIT 5
");
$pendingStories = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'stats' => $stats,
    'recent_officers' => $recentOfficers,
    'pending_stories' => $pendingStories
]);
?>
