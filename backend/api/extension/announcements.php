<?php
/**
 * AgriConnect - Extension Officer Announcements API
 * GET    - List announcements by this officer
 * POST   - Create a new announcement
 * PUT    - Update an announcement
 * DELETE - Remove an announcement
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
            SELECT a.*, l.name as location_name
            FROM announcements a
            LEFT JOIN locations l ON l.id = a.location_id
            WHERE a.created_by = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$eoId]);
        jsonResponse(['success' => true, 'announcements' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $input = getJsonInput();

        if (empty($input['title']) || empty($input['content'])) {
            jsonResponse(['error' => 'Title and content are required'], 400);
        }

        $locationId = !empty($input['location_id']) ? (int)$input['location_id'] : $officer['location_id'];

        $stmt = $db->prepare("
            INSERT INTO announcements (title, content, target_audience, created_by, location_id, priority)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($input['title']),
            trim($input['content']),
            $input['target_audience'] ?? 'all',
            $eoId,
            $locationId,
            $input['priority'] ?? 'medium'
        ]);

        $announcementId = $db->lastInsertId();

        // Notify farmers in this location
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
                VALUES (?, ?, ?, 'announcement')
            ");
            $stmt->execute([
                $farmer['id'],
                'New Announcement: ' . trim($input['title']),
                substr(trim($input['content']), 0, 200)
            ]);
        }

        jsonResponse(['success' => true, 'id' => $announcementId, 'message' => 'Announcement posted and farmers notified'], 201);
        break;

    case 'PUT':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Announcement ID is required'], 400);
        }

        $stmt = $db->prepare("
            UPDATE announcements SET title = ?, content = ?, priority = ?, status = ?
            WHERE id = ? AND created_by = ?
        ");
        $stmt->execute([
            trim($input['title']),
            trim($input['content']),
            $input['priority'] ?? 'medium',
            $input['status'] ?? 'active',
            (int)$input['id'],
            $eoId
        ]);

        jsonResponse(['success' => true, 'message' => 'Announcement updated']);
        break;

    case 'DELETE':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Announcement ID is required'], 400);
        }

        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'announcements', (int)$input['id'], 'Announcement');

        $stmt = $db->prepare("DELETE FROM announcements WHERE id = ? AND created_by = ?");
        $stmt->execute([(int)$input['id'], $eoId]);

        jsonResponse(['success' => true, 'message' => 'Announcement deleted']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
