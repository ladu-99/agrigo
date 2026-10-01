<?php
/**
 * AgriConnect - Extension Officer Resources API
 * GET    - List resources uploaded by this officer
 * POST   - Upload a new resource
 * PUT    - Update a resource
 * DELETE - Remove a resource
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$officer = requireRole('extension_officer');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$eoId = $officer['extension_officer_id'];

switch ($method) {
    case 'GET':
        $stmt = $db->prepare("
            SELECT r.*, l.name as target_location_name
            FROM resources r
            LEFT JOIN locations l ON l.id = r.target_location_id
            WHERE r.uploaded_by = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$eoId]);
        $resources = $stmt->fetchAll();

        jsonResponse(['success' => true, 'resources' => $resources]);
        break;

    case 'POST':
        $input = getJsonInput();

        if (empty($input['title']) || empty($input['type']) || empty($input['file_path'])) {
            jsonResponse(['error' => 'Title, type, and file path are required'], 400);
        }

        $stmt = $db->prepare("
            INSERT INTO resources (title, description, type, file_path, uploaded_by, category, target_location_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($input['title']),
            $input['description'] ?? null,
            $input['type'],
            $input['file_path'],
            $eoId,
            $input['category'] ?? null,
            !empty($input['target_location_id']) ? (int)$input['target_location_id'] : null
        ]);

        // Notify farmers about new resource
        $stmt = $db->prepare("
            SELECT u.id FROM users u
            JOIN farmers f ON f.user_id = u.id
            WHERE f.assigned_extension_officer_id = ?
        ");
        $stmt->execute([$eoId]);
        $farmers = $stmt->fetchAll();

        foreach ($farmers as $farmer) {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'New Learning Resource', 'A new resource has been uploaded: " . trim($input['title']) . "', 'resource')
            ");
            $stmt->execute([$farmer['id']]);
        }

        jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Resource uploaded'], 201);
        break;

    case 'PUT':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Resource ID is required'], 400);
        }

        $stmt = $db->prepare("
            UPDATE resources SET title = ?, description = ?, category = ?, status = ?
            WHERE id = ? AND uploaded_by = ?
        ");
        $stmt->execute([
            trim($input['title']),
            $input['description'] ?? null,
            $input['category'] ?? null,
            $input['status'] ?? 'active',
            (int)$input['id'],
            $eoId
        ]);

        jsonResponse(['success' => true, 'message' => 'Resource updated']);
        break;

    case 'DELETE':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Resource ID is required'], 400);
        }

        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'resources', (int)$input['id'], 'Resource');

        $stmt = $db->prepare("DELETE FROM resources WHERE id = ? AND uploaded_by = ?");
        $stmt->execute([(int)$input['id'], $eoId]);

        jsonResponse(['success' => true, 'message' => 'Resource deleted']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
