<?php
/**
 * AgriConnect - Public Opportunities API
 */
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$db = getDB();
$stmt = $db->query("SELECT * FROM opportunities WHERE status = 'active' ORDER BY deadline ASC");
echo json_encode(['success' => true, 'opportunities' => $stmt->fetchAll()]);
?>
