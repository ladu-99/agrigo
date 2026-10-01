<?php
/**
 * AgriConnect - Users Management API
 * GET - List all users with optional filters
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
requireRole('admin');
$db = getDB();

$role = isset($_GET['role']) ? $_GET['role'] : null;
$locationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

$sql = "
    SELECT u.id, u.name, u.email, u.phone, u.role, u.status, u.location_id, u.created_at,
           l.name as location_name
    FROM users u
    LEFT JOIN locations l ON l.id = u.location_id
    WHERE 1=1
";
$params = [];

if ($role) {
    $sql .= " AND u.role = ?";
    $params[] = $role;
}
if ($locationId) {
    $sql .= " AND u.location_id = ?";
    $params[] = $locationId;
}
if ($search) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

jsonResponse(['success' => true, 'users' => $users]);
?>
