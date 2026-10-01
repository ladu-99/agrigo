<?php
/**
 * AgriConnect - Extension Officer Dashboard API
 * GET - Dashboard stats for extension officer
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$officer = requireRole('extension_officer');
$db = getDB();

$eoId = $officer['extension_officer_id'];
$locationId = $officer['location_id'];

$stats = [];

// Count assigned farmers
$stmt = $db->prepare("SELECT COUNT(*) as count FROM farmers WHERE assigned_extension_officer_id = ?");
$stmt->execute([$eoId]);
$stats['farmers'] = (int)$stmt->fetch()['count'];

// Count resources uploaded
$stmt = $db->prepare("SELECT COUNT(*) as count FROM resources WHERE uploaded_by = ?");
$stmt->execute([$eoId]);
$stats['resources'] = (int)$stmt->fetch()['count'];

// Count announcements posted
$stmt = $db->prepare("SELECT COUNT(*) as count FROM announcements WHERE created_by = ?");
$stmt->execute([$eoId]);
$stats['announcements'] = (int)$stmt->fetch()['count'];

// Count market listings
$stmt = $db->prepare("SELECT COUNT(*) as count FROM market_listings WHERE added_by = ? AND status = 'active'");
$stmt->execute([$eoId]);
$stats['market_listings'] = (int)$stmt->fetch()['count'];

// Count cooperatives
$stmt = $db->prepare("SELECT COUNT(*) as count FROM cooperatives WHERE extension_officer_id = ? AND status = 'active'");
$stmt->execute([$eoId]);
$stats['cooperatives'] = (int)$stmt->fetch()['count'];

// Sales stats
$stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM marketplace_sales WHERE extension_officer_id = ?");
$stmt->execute([$eoId]);
$salesRow = $stmt->fetch();
$stats['total_sales'] = (int)$salesRow['count'];
$stats['total_revenue'] = (float)$salesRow['revenue'];

// Recent announcements
$stmt = $db->prepare("
    SELECT id, title, priority, created_at FROM announcements
    WHERE created_by = ? ORDER BY created_at DESC LIMIT 5
");
$stmt->execute([$eoId]);
$recentAnnouncements = $stmt->fetchAll();

// Recent resources
$stmt = $db->prepare("
    SELECT id, title, type, category, created_at FROM resources
    WHERE uploaded_by = ? ORDER BY created_at DESC LIMIT 5
");
$stmt->execute([$eoId]);
$recentResources = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'stats' => $stats,
    'recent_announcements' => $recentAnnouncements,
    'recent_resources' => $recentResources
]);
?>
