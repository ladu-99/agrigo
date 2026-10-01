<?php
/**
 * AgriConnect - Specializations API
 * GET - List all specializations
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
requireRole('admin');
$db = getDB();

$stmt = $db->query("SELECT * FROM specializations ORDER BY name ASC");
$specializations = $stmt->fetchAll();

jsonResponse(['success' => true, 'specializations' => $specializations]);
?>
