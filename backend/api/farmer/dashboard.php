<?php
/**
 * AgriConnect - Farmer Dashboard API
 * GET - Dashboard data for farmer
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$farmer = requireRole(['farmer']);
$db = getDB();
$userId = $farmer['id'];

// Get farmer details
$stmt = $db->prepare("SELECT f.*, l.name as location_name FROM farmers f LEFT JOIN locations l ON l.id = f.location_id WHERE f.user_id = ?");
$stmt->execute([$userId]);
$farmerDetails = $stmt->fetch();

// Get farmer's extension officer
$eoId = $farmerDetails['assigned_extension_officer_id'] ?? null;
$officerName = '';
if ($eoId) {
    $stmt = $db->prepare("SELECT u.name FROM extension_officers eo JOIN users u ON u.id = eo.user_id WHERE eo.id = ?");
    $stmt->execute([$eoId]);
    $eo = $stmt->fetch();
    $officerName = $eo ? $eo['name'] : '';
}

// Recent announcements for farmer's location
$stmt = $db->prepare("
    SELECT a.id, a.title, a.content, a.priority, a.created_at, l.name as location_name
    FROM announcements a
    LEFT JOIN locations l ON l.id = a.location_id
    WHERE a.location_id = ? AND a.status = 'active'
    ORDER BY a.priority DESC, a.created_at DESC LIMIT 5
");
$stmt->execute([$farmerDetails['location_id'] ?? 0]);
$announcements = $stmt->fetchAll();

// Recent resources
if ($eoId) {
    $stmt = $db->prepare("
        SELECT r.id, r.title, r.type, r.category, r.file_path, r.created_at
        FROM resources r
        WHERE r.uploaded_by = ? AND r.status = 'active'
        ORDER BY r.created_at DESC LIMIT 5
    ");
    $stmt->execute([$eoId]);
} else {
    $stmt = $db->prepare("SELECT id, title, type, category, file_path, created_at FROM resources WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
}
$resources = $stmt->fetchAll();

// Unread notifications count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetch()['count'];

// Market trends for farmer's location
$stmt = $db->prepare("
    SELECT mt.*, l.name as location_name
    FROM market_trends mt
    LEFT JOIN locations l ON l.id = mt.location_id
    WHERE mt.location_id = ?
    ORDER BY mt.recorded_date DESC LIMIT 10
");
$stmt->execute([$farmerDetails['location_id'] ?? 0]);
$marketTrends = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'farmer' => $farmerDetails,
    'officer_name' => $officerName,
    'unread_notifications' => $unreadCount,
    'announcements' => $announcements,
    'resources' => $resources,
    'market_trends' => $marketTrends
]);
?>
