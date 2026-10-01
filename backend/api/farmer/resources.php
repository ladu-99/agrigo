<?php
/**
 * AgriConnect - Farmer Resources API
 * GET - List resources available to this farmer (by location or general)
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$farmer = requireRole('farmer');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get farmer's location
$stmt = $db->prepare("SELECT location_id FROM farmers WHERE user_id = ?");
$stmt->execute([$farmer['id']]);
$farmerRow = $stmt->fetch();
$locationId = $farmerRow['location_id'] ?? $farmer['location_id'];

// Get resources available to this farmer:
// - Resources targeting their location
// - Resources with no specific location (general resources)
// - Filter by type if requested
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['video', 'document']) ? $_GET['type'] : null;
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : null;
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : null;

$sql = "
    SELECT r.*, u.name as uploader_name,
    (SELECT eo2.user_id FROM extension_officers eo2 WHERE eo2.id = r.uploaded_by) as uploader_user_id
    FROM resources r
    LEFT JOIN extension_officers eo ON eo.id = r.uploaded_by
    LEFT JOIN users u ON u.id = eo.user_id
    WHERE r.status = 'active'
    AND (r.target_location_id = ? OR r.target_location_id IS NULL)
";
$params = [$locationId];

if ($typeFilter) {
    $sql .= " AND r.type = ?";
    $params[] = $typeFilter;
}

if ($categoryFilter) {
    $sql .= " AND r.category = ?";
    $params[] = $categoryFilter;
}

if ($searchFilter) {
    $sql .= " AND (r.title LIKE ? OR r.description LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get list of categories for filtering
$stmt = $db->query("SELECT DISTINCT category FROM resources WHERE status = 'active' AND category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

jsonResponse([
    'success' => true,
    'resources' => $resources,
    'categories' => $categories,
    'total' => count($resources)
]);
?>
