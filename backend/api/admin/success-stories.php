<?php
/**
 * AgriConnect - Success Stories Management API
 * GET - List success stories (with optional status filter)
 * PUT - Approve or reject a story
 * DELETE - Delete a story
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$admin = requireRole('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $status = isset($_GET['status']) ? $_GET['status'] : null;

        $sql = "
            SELECT ss.*, l.name as location_name, u.name as approved_by_name
            FROM success_stories ss
            LEFT JOIN locations l ON l.id = ss.location_id
            LEFT JOIN users u ON u.id = ss.approved_by
            WHERE 1=1
        ";
        $params = [];

        if ($status) {
            $sql .= " AND ss.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY ss.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $stories = $stmt->fetchAll();

        jsonResponse(['success' => true, 'stories' => $stories]);
        break;

    case 'PUT':
        $input = getJsonInput();

        if (empty($input['id']) || empty($input['action'])) {
            jsonResponse(['error' => 'Story ID and action (approve/reject) are required'], 400);
        }

        $newStatus = ($input['action'] === 'approve') ? 'approved' : 'rejected';

        $stmt = $db->prepare("
            UPDATE success_stories SET status = ?, approved_by = ? WHERE id = ?
        ");
        $stmt->execute([$newStatus, $admin['id'], (int)$input['id']]);

        jsonResponse(['success' => true, 'message' => 'Story ' . $newStatus]);
        break;

    case 'DELETE':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Story ID is required'], 400);
        }

        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'success_stories', (int)$input['id'], 'Success Story');

        $stmt = $db->prepare("DELETE FROM success_stories WHERE id = ?");
        $stmt->execute([(int)$input['id']]);

        jsonResponse(['success' => true, 'message' => 'Story deleted']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
