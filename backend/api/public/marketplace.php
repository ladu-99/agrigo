<?php
/**
 * AgriConnect - Public Marketplace API
 * Public marketplace browsing with category, price, and location
 * sorting/filtering. No authentication required.
 * GET - Returns active listings + available filter options.
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$db = getDB();

$locationId = isset($_GET['location_id']) && $_GET['location_id'] !== '' ? (int)$_GET['location_id'] : null;
$type = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null;
$search = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : null;
$sort = $_GET['sort'] ?? 'newest';

$where = ["ml.status = 'active'"];
$params = [];

if ($locationId) {
    $where[] = "ml.location_id = ?";
    $params[] = $locationId;
}
if ($type) {
    $where[] = "ml.product_type = ?";
    $params[] = $type;
}
if ($search) {
    $where[] = "(ml.product_name LIKE ? OR ml.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Whitelisted ORDER BY so the sort value is never interpolated raw.
$orderBy = 'ml.created_at DESC';
switch ($sort) {
    case 'price_asc':
        $orderBy = 'ml.price_ssp ASC';
        break;
    case 'price_desc':
        $orderBy = 'ml.price_ssp DESC';
        break;
    case 'name_asc':
        $orderBy = 'ml.product_name ASC';
        break;
    case 'name_desc':
        $orderBy = 'ml.product_name DESC';
        break;
    case 'newest':
    default:
        $orderBy = 'ml.created_at DESC';
        break;
}

$whereSql = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT ml.id, ml.product_name, ml.description, ml.quantity, ml.quantity_unit,
           ml.price_ssp, ml.product_photo, ml.product_type, ml.seller_type, ml.location_id, ml.added_by,
           l.name as location_name
    FROM market_listings ml
    LEFT JOIN locations l ON ml.location_id = l.id
    WHERE $whereSql
    ORDER BY $orderBy
");
$stmt->execute($params);
$listings = $stmt->fetchAll();

// Filter options derived from currently-active listings.
$locations = $db->query("
    SELECT DISTINCT l.id, l.name
    FROM market_listings ml
    JOIN locations l ON ml.location_id = l.id
    WHERE ml.status = 'active'
    ORDER BY l.name ASC
")->fetchAll();

$categories = $db->query("
    SELECT DISTINCT ml.product_type
    FROM market_listings ml
    WHERE ml.status = 'active'
    ORDER BY ml.product_type ASC
")->fetchAll(PDO::FETCH_COLUMN);

jsonResponse([
    'success' => true,
    'listings' => $listings,
    'locations' => $locations,
    'categories' => $categories,
]);
