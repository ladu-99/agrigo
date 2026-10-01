<?php
/**
 * AgriConnect - Public Success Stories API
 */
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$db = getDB();
$stmt = $db->query("SELECT ss.*, l.name as location_name FROM success_stories ss LEFT JOIN locations l ON l.id = ss.location_id WHERE ss.status = 'approved' ORDER BY ss.created_at DESC");
echo json_encode(['success' => true, 'stories' => $stmt->fetchAll()]);
?>
