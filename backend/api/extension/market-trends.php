<?php
/**
 * AgriConnect - Extension Officer Market Trends API
 * GET  - List market trends for this officer's location
 * POST - Add/update a market trend entry
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$officer = requireRole('extension_officer');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$eoId = $officer['extension_officer_id'];
$locationId = $officer['location_id'];

switch ($method) {
    case 'GET':
        $stmt = $db->prepare("
            SELECT mt.*, l.name as location_name
            FROM market_trends mt
            LEFT JOIN locations l ON l.id = mt.location_id
            WHERE mt.location_id = ?
            ORDER BY mt.recorded_date DESC, mt.commodity ASC
        ");
        $stmt->execute([$locationId]);
        jsonResponse(['success' => true, 'trends' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $input = getJsonInput();

        if (empty($input['commodity']) || empty($input['price'])) {
            jsonResponse(['error' => 'Commodity name and price are required'], 400);
        }

        $stmt = $db->prepare("
            INSERT INTO market_trends (commodity, price, unit, location_id, trend, recorded_date, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($input['commodity']),
            (float)$input['price'],
            $input['unit'] ?? 'kg',
            (int)($input['location_id'] ?? $locationId),
            $input['trend'] ?? 'stable',
            $input['recorded_date'] ?? date('Y-m-d'),
            $eoId
        ]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Market trend recorded'], 201);
        break;

    case 'PUT':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Trend ID is required'], 400);
        }

        $stmt = $db->prepare("
            UPDATE market_trends SET commodity = ?, price = ?, unit = ?, trend = ?, recorded_date = ?
            WHERE id = ? AND updated_by = ?
        ");
        $stmt->execute([
            trim($input['commodity']),
            (float)$input['price'],
            $input['unit'] ?? 'kg',
            $input['trend'] ?? 'stable',
            $input['recorded_date'] ?? date('Y-m-d'),
            (int)$input['id'],
            $eoId
        ]);

        jsonResponse(['success' => true, 'message' => 'Trend updated']);
        break;

    case 'DELETE':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Trend ID is required'], 400);
        }

        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'market_trends', (int)$input['id'], 'Market Trend');

        $stmt = $db->prepare("DELETE FROM market_trends WHERE id = ? AND updated_by = ?");
        $stmt->execute([(int)$input['id'], $eoId]);

        jsonResponse(['success' => true, 'message' => 'Trend deleted']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
