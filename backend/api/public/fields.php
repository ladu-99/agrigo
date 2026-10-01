<?php
/**
 * AgriConnect - Public Agricultural Fields API
 */
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$db = getDB();
$stmt = $db->query("SELECT af.*, l.name as location_name FROM agricultural_fields af LEFT JOIN locations l ON l.id = af.location_id WHERE af.status = 'active' ORDER BY af.created_at DESC");
echo json_encode(['success' => true, 'fields' => $stmt->fetchAll()]);
?>
