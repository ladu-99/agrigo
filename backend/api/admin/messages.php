<?php
/**
 * AgriConnect - Admin Public Messages API
 * Direct messages sent by visitors from the public contact form.
 * GET    - List messages (optional ?status= filter) + counts
 * PUT    - Update a message's status (new / read / archived)
 * DELETE - Remove a message (goes to Trash)
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$admin = requireRole('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

        if ($status) {
            $stmt = $db->prepare("SELECT * FROM contact_messages WHERE status = ? ORDER BY created_at DESC");
            $stmt->execute([$status]);
        } else {
            $stmt = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
        }
        $messages = $stmt->fetchAll();

        $counts = $db->query("
            SELECT
                COUNT(*) total,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) cnt_new,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) cnt_read,
                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) cnt_archived
            FROM contact_messages
        ")->fetch();

        jsonResponse(['success' => true, 'messages' => $messages, 'counts' => $counts]);
        break;

    case 'PUT':
        $input = getJsonInput();
        if (empty($input['id']) || empty($input['status'])) {
            jsonResponse(['error' => 'Message ID and status are required'], 400);
        }
        if (!in_array($input['status'], ['new', 'read', 'archived'], true)) {
            jsonResponse(['error' => 'Invalid status'], 400);
        }
        $stmt = $db->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
        $stmt->execute([$input['status'], (int)$input['id']]);
        jsonResponse(['success' => true, 'message' => 'Message updated']);
        break;

    case 'DELETE':
        $input = getJsonInput();
        if (empty($input['id'])) {
            jsonResponse(['error' => 'Message ID is required'], 400);
        }
        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'contact_messages', (int)$input['id'], 'Public Message');

        $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([(int)$input['id']]);
        jsonResponse(['success' => true, 'message' => 'Message moved to Trash']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
