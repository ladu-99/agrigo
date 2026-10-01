<?php
/**
 * AgriConnect - Admin Officer Reports API
 * Reports forwarded by extension officers.
 * GET    - List all reports (optional ?status= & ?type= filters) + counts
 * PUT    - Update a report's status (new / reviewed / archived)
 * DELETE - Remove a report (goes to Trash)
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$admin = requireRole('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
        $type = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null;

        $where = [];
        $params = [];
        if ($status) { $where[] = "r.status = ?"; $params[] = $status; }
        if ($type) { $where[] = "r.report_type = ?"; $params[] = $type; }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $db->prepare("
            SELECT r.*, u.name AS officer_name, u.email AS officer_email, l.name AS location_name
            FROM officer_reports r
            JOIN extension_officers eo ON eo.id = r.extension_officer_id
            JOIN users u ON u.id = eo.user_id
            LEFT JOIN locations l ON l.id = eo.location_id
            $whereSql
            ORDER BY r.created_at DESC
        ");
        $stmt->execute($params);
        $reports = $stmt->fetchAll();

        $counts = $db->query("
            SELECT
                COUNT(*) total,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) cnt_new,
                SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) cnt_reviewed,
                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) cnt_archived
            FROM officer_reports
        ")->fetch();

        jsonResponse(['success' => true, 'reports' => $reports, 'counts' => $counts]);
        break;

    case 'PUT':
        $input = getJsonInput();
        if (empty($input['id']) || empty($input['status'])) {
            jsonResponse(['error' => 'Report ID and status are required'], 400);
        }
        if (!in_array($input['status'], ['new', 'reviewed', 'archived'], true)) {
            jsonResponse(['error' => 'Invalid status'], 400);
        }
        $stmt = $db->prepare("UPDATE officer_reports SET status = ? WHERE id = ?");
        $stmt->execute([$input['status'], (int)$input['id']]);
        jsonResponse(['success' => true, 'message' => 'Report updated']);
        break;

    case 'DELETE':
        $input = getJsonInput();
        if (empty($input['id'])) {
            jsonResponse(['error' => 'Report ID is required'], 400);
        }
        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'officer_reports', (int)$input['id'], 'Officer Report');

        $stmt = $db->prepare("DELETE FROM officer_reports WHERE id = ?");
        $stmt->execute([(int)$input['id']]);
        jsonResponse(['success' => true, 'message' => 'Report moved to Trash']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
