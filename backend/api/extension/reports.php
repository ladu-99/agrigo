<?php
/**
 * AgriConnect - Extension Officer Reports API
 * Officers forward field/market/activity reports to the admin, optionally
 * attaching a file (PDF / Word / image).
 * GET    - List this officer's submitted reports
 * POST   - Submit a new report (multipart/form-data or JSON)
 * DELETE - Remove one of this officer's reports (goes to Trash)
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
            SELECT r.*, u.name as officer_name
            FROM officer_reports r
            JOIN users u ON u.id = ?
            WHERE r.extension_officer_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$officer['id'], $eoId]);
        jsonResponse(['success' => true, 'reports' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        $isMultipart = strpos($contentType, 'multipart/form-data') !== false;

        if ($isMultipart) {
            $src = $_POST;
        } else {
            $src = getJsonInput();
            if (!is_array($src)) $src = [];
        }

        $title = trim($src['title'] ?? '');
        $reportType = $src['report_type'] ?? 'field';
        $periodStart = !empty($src['period_start']) ? $src['period_start'] : null;
        $periodEnd = !empty($src['period_end']) ? $src['period_end'] : null;
        $description = trim($src['description'] ?? '');

        if ($title === '' || $description === '') {
            jsonResponse(['error' => 'Title and description are required'], 400);
        }
        $allowedTypes = ['field', 'market', 'activity', 'sales', 'other'];
        if (!in_array($reportType, $allowedTypes, true)) {
            $reportType = 'field';
        }

        // Optional file attachment
        $filePath = null;
        if ($isMultipart && isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/reports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $file = $_FILES['report_file'];
            $allowedMimes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg', 'image/png', 'image/webp'
            ];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimes)) {
                jsonResponse(['error' => 'Attachment must be a PDF, Word doc, or image'], 400);
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                jsonResponse(['error' => 'Attachment must be less than 10MB'], 400);
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'report_' . time() . '_' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                jsonResponse(['error' => 'Failed to upload attachment'], 500);
            }
            $filePath = 'reports/' . $filename;
        }

        $stmt = $db->prepare("
            INSERT INTO officer_reports
            (extension_officer_id, title, report_type, period_start, period_end, description, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$eoId, $title, $reportType, $periodStart, $periodEnd, $description, $filePath]);
        $reportId = $db->lastInsertId();

        // Notify admins
        $admins = $db->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        if ($admins) {
            $nstmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'system', 0)");
            foreach ($admins as $adminId) {
                $nstmt->execute([$adminId, 'New report from ' . $officer['name'], substr($title, 0, 140)]);
            }
        }

        jsonResponse(['success' => true, 'id' => $reportId, 'file_path' => $filePath, 'message' => 'Report sent to admin'], 201);
        break;

    case 'DELETE':
        $input = getJsonInput();
        if (empty($input['id'])) {
            jsonResponse(['error' => 'Report ID is required'], 400);
        }
        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'officer_reports', (int)$input['id'], 'Officer Report');

        $stmt = $db->prepare("DELETE FROM officer_reports WHERE id = ? AND extension_officer_id = ?");
        $stmt->execute([(int)$input['id'], $eoId]);

        jsonResponse(['success' => true, 'message' => 'Report removed']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
