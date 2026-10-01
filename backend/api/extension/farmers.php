<?php
/**
 * AgriConnect - Extension Officer Farmers Management API
 * GET - List farmers assigned to this officer
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$officer = requireRole('extension_officer');
$db = getDB();
$eoId = $officer['extension_officer_id'];

$stmt = $db->prepare("
    SELECT f.*, u.name, u.email, u.phone, u.status, u.profile_photo, l.name as location_name
    FROM farmers f
    JOIN users u ON u.id = f.user_id
    LEFT JOIN locations l ON l.id = f.location_id
    WHERE f.assigned_extension_officer_id = ?
    ORDER BY (u.status = 'pending') DESC, u.created_at DESC
");
$stmt->execute([$eoId]);
$farmers = $stmt->fetchAll();

jsonResponse(['success' => true, 'farmers' => $farmers]);
?>
