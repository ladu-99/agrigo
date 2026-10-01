<?php
/**
 * AgriConnect - Farmer Notifications API
 * GET    - List notifications for the farmer
 * PUT    - Mark notification as read
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$farmer = requireRole(['farmer', 'cooperative_leader']);
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$userId = $farmer['id'];

switch ($method) {
    case 'GET':
        $stmt = $db->prepare("
            SELECT * FROM notifications WHERE user_id = ?
            ORDER BY created_at DESC LIMIT 50
        ");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $unreadCount = (int)$stmt->fetch()['count'];

        jsonResponse(['success' => true, 'notifications' => $notifications, 'unread_count' => $unreadCount]);
        break;

    case 'PUT':
        $input = getJsonInput();

        if (!empty($input['mark_all_read'])) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            jsonResponse(['success' => true, 'message' => 'All notifications marked as read']);
        }

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Notification ID is required'], 400);
        }

        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([(int)$input['id'], $userId]);

        jsonResponse(['success' => true, 'message' => 'Notification marked as read']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
