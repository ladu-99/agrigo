<?php
/**
 * AgriConnect - Locations Management API
 * GET  - List all locations (with hierarchy)
 * POST - Create a new location
 * PUT  - Update a location
 * DELETE - Delete a location
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
requireRole('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $type = isset($_GET['type']) ? $_GET['type'] : null;
        $parentId = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;

        $sql = "SELECT l.*, p.name as parent_name FROM locations l LEFT JOIN locations p ON p.id = l.parent_id WHERE 1=1";
        $params = [];

        if ($type) {
            $sql .= " AND l.type = ?";
            $params[] = $type;
        }
        if ($parentId !== null) {
            $sql .= " AND l.parent_id = ?";
            $params[] = $parentId;
        }

        $sql .= " ORDER BY l.type ASC, l.name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $locations = $stmt->fetchAll();

        jsonResponse(['success' => true, 'locations' => $locations]);
        break;

    case 'POST':
        $input = getJsonInput();

        if (empty($input['name']) || empty($input['type'])) {
            jsonResponse(['error' => 'Name and type are required'], 400);
        }

        $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

        $stmt = $db->prepare("INSERT INTO locations (name, type, parent_id) VALUES (?, ?, ?)");
        $stmt->execute([trim($input['name']), $input['type'], $parentId]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Location created'], 201);
        break;

    case 'PUT':
        $input = getJsonInput();

        if (empty($input['id']) || empty($input['name'])) {
            jsonResponse(['error' => 'ID and name are required'], 400);
        }

        $stmt = $db->prepare("UPDATE locations SET name = ?, type = ?, parent_id = ? WHERE id = ?");
        $stmt->execute([
            trim($input['name']),
            $input['type'] ?? 'state',
            !empty($input['parent_id']) ? (int)$input['parent_id'] : null,
            (int)$input['id']
        ]);

        jsonResponse(['success' => true, 'message' => 'Location updated']);
        break;

    case 'DELETE':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Location ID is required'], 400);
        }

        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'locations', (int)$input['id'], 'Location');

        $stmt = $db->prepare("DELETE FROM locations WHERE id = ?");
        $stmt->execute([(int)$input['id']]);

        jsonResponse(['success' => true, 'message' => 'Location deleted']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
