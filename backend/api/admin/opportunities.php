<?php
/**
 * AgriConnect - Opportunities Management API
 * GET    - List all opportunities
 * POST   - Create a new opportunity
 * PUT    - Update an opportunity
 * DELETE - Delete an opportunity
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
requireRole('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $db->query("SELECT * FROM opportunities ORDER BY created_at DESC");
        $opportunities = $stmt->fetchAll();
        jsonResponse(['success' => true, 'opportunities' => $opportunities]);
        break;

    case 'POST':
        $input = getJsonInput();

        if (empty($input['title']) || empty($input['description'])) {
            jsonResponse(['error' => 'Title and description are required'], 400);
        }

        $stmt = $db->prepare("
            INSERT INTO opportunities (title, description, source, type, deadline, contact_info, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            trim($input['title']),
            trim($input['description']),
            $input['source'] ?? 'ngo',
            $input['type'] ?? 'funding',
            !empty($input['deadline']) ? $input['deadline'] : null,
            $input['contact_info'] ?? null
        ]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Opportunity created'], 201);
        break;

    case 'PUT':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Opportunity ID is required'], 400);
        }

        $stmt = $db->prepare("
            UPDATE opportunities SET title = ?, description = ?, source = ?, type = ?, deadline = ?, contact_info = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($input['title']),
            trim($input['description']),
            $input['source'] ?? 'ngo',
            $input['type'] ?? 'funding',
            !empty($input['deadline']) ? $input['deadline'] : null,
            $input['contact_info'] ?? null,
            $input['status'] ?? 'active',
            (int)$input['id']
        ]);

        jsonResponse(['success' => true, 'message' => 'Opportunity updated']);
        break;

    case 'DELETE':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Opportunity ID is required'], 400);
        }

        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'opportunities', (int)$input['id'], 'Opportunity');

        $stmt = $db->prepare("DELETE FROM opportunities WHERE id = ?");
        $stmt->execute([(int)$input['id']]);

        jsonResponse(['success' => true, 'message' => 'Opportunity deleted']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
