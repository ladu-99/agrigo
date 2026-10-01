<?php
/**
 * AgriConnect - Extension Officer Marketplace Inquiries API
 * Buyer inquiries received on the officer's own product listings.
 * GET    - List inquiries for this officer's listings (+ summary counts)
 * PUT    - Update an inquiry's status (new / contacted / completed / closed)
 * DELETE - Remove an inquiry
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$officer = requireRole('extension_officer');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$eoId = $officer['extension_officer_id'];

switch ($method) {
    case 'GET':
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
        $listingId = isset($_GET['listing_id']) && $_GET['listing_id'] !== '' ? (int)$_GET['listing_id'] : null;

        $where = ["ml.added_by = ?"];
        $params = [$eoId];
        if ($status) {
            $where[] = "pi.status = ?";
            $params[] = $status;
        }
        if ($listingId) {
            $where[] = "pi.listing_id = ?";
            $params[] = $listingId;
        }
        $whereSql = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT pi.*, ml.product_name, ml.price_ssp, ml.quantity_unit, ml.quantity as available_quantity,
                   l.name as location_name
            FROM product_inquiries pi
            JOIN market_listings ml ON ml.id = pi.listing_id
            LEFT JOIN locations l ON l.id = ml.location_id
            WHERE $whereSql
            ORDER BY pi.created_at DESC
        ");
        $stmt->execute($params);
        $inquiries = $stmt->fetchAll();

        // Summary counts across all of this officer's inquiries.
        $statsStmt = $db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN pi.status = 'new' THEN 1 ELSE 0 END) as cnt_new,
                SUM(CASE WHEN pi.status = 'contacted' THEN 1 ELSE 0 END) as cnt_contacted,
                SUM(CASE WHEN pi.status = 'completed' THEN 1 ELSE 0 END) as cnt_completed,
                SUM(CASE WHEN pi.status = 'closed' THEN 1 ELSE 0 END) as cnt_closed
            FROM product_inquiries pi
            JOIN market_listings ml ON ml.id = pi.listing_id
            WHERE ml.added_by = ?
        ");
        $statsStmt->execute([$eoId]);
        $stats = $statsStmt->fetch();

        jsonResponse([
            'success' => true,
            'inquiries' => $inquiries,
            'stats' => $stats
        ]);
        break;

    case 'PUT':
        $input = getJsonInput();
        if (empty($input['id']) || empty($input['status'])) {
            jsonResponse(['error' => 'Inquiry ID and status are required'], 400);
        }
        $allowed = ['new', 'contacted', 'completed', 'closed'];
        if (!in_array($input['status'], $allowed, true)) {
            jsonResponse(['error' => 'Invalid status'], 400);
        }

        // Only allow updating inquiries that belong to this officer's listings.
        $stmt = $db->prepare("
            UPDATE product_inquiries pi
            JOIN market_listings ml ON ml.id = pi.listing_id
            SET pi.status = ?
            WHERE pi.id = ? AND ml.added_by = ?
        ");
        $stmt->execute([$input['status'], (int)$input['id'], $eoId]);

        jsonResponse(['success' => true, 'message' => 'Inquiry updated']);
        break;

    case 'DELETE':
        $input = getJsonInput();
        if (empty($input['id'])) {
            jsonResponse(['error' => 'Inquiry ID is required'], 400);
        }
        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'product_inquiries', (int)$input['id'], 'Buyer Inquiry');

        $stmt = $db->prepare("
            DELETE pi FROM product_inquiries pi
            JOIN market_listings ml ON ml.id = pi.listing_id
            WHERE pi.id = ? AND ml.added_by = ?
        ");
        $stmt->execute([(int)$input['id'], $eoId]);

        jsonResponse(['success' => true, 'message' => 'Inquiry removed']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
