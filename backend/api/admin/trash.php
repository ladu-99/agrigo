<?php
/**
 * AgriConnect - Admin Trash / Recycle Bin API
 * Central list of every deleted record (snapshots). Admins can restore an
 * item back to its source table, or permanently purge it.
 * GET  - List trashed items (default: not-yet-restored)
 * POST - { action: 'restore' | 'purge' | 'empty', id? }
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/trash.php';

setCorsHeaders();
$admin = requireRole('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $showRestored = isset($_GET['all']) && $_GET['all'] === '1';
        $sql = "
            SELECT t.id, t.entity_type, t.source_table, t.entity_id, t.label, t.snapshot,
                   t.deleted_at, t.restored_at, u.name AS deleted_by_name
            FROM trash t
            LEFT JOIN users u ON u.id = t.deleted_by
        ";
        if (!$showRestored) {
            $sql .= "WHERE t.restored_at IS NULL ";
        }
        $sql .= "ORDER BY t.deleted_at DESC";
        $items = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$it) {
            $snap = json_decode($it['snapshot'], true);
            unset($snap['password_hash']); // never expose hashes in the list
            $it['preview'] = is_array($snap) ? $snap : [];
            unset($it['snapshot']);
        }
        unset($it);

        $count = (int)$db->query("SELECT COUNT(*) FROM trash WHERE restored_at IS NULL")->fetchColumn();

        jsonResponse(['success' => true, 'items' => $items, 'count' => $count]);
        break;

    case 'POST':
        $input = getJsonInput();
        $action = $input['action'] ?? '';

        if ($action === 'restore') {
            if (empty($input['id'])) {
                jsonResponse(['error' => 'Trash item ID is required'], 400);
            }
            $result = trash_restore($db, (int)$input['id']);
            if (!$result['ok']) {
                jsonResponse(['error' => $result['error']], 400);
            }
            jsonResponse(['success' => true, 'restored_id' => $result['restored_id'], 'message' => 'Item restored']);
        }

        if ($action === 'purge') {
            if (empty($input['id'])) {
                jsonResponse(['error' => 'Trash item ID is required'], 400);
            }
            $stmt = $db->prepare("DELETE FROM trash WHERE id = ?");
            $stmt->execute([(int)$input['id']]);
            jsonResponse(['success' => true, 'message' => 'Permanently deleted']);
        }

        if ($action === 'empty') {
            $db->exec("DELETE FROM trash WHERE restored_at IS NULL");
            jsonResponse(['success' => true, 'message' => 'Trash emptied']);
        }

        jsonResponse(['error' => 'Unknown action'], 400);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
