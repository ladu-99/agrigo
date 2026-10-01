<?php
/**
 * AgriConnect - Extension Officer Farmer Profile API
 * GET  ?user_id=X        - View a farmer's full profile (photo + details + cooperative)
 * POST action=photo      - Upload / update the farmer's profile photo (multipart, field "photo")
 *
 * Officers may only access farmers assigned to them.
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$officer = requireRole('extension_officer');
$db = getDB();
$eoId = $officer['extension_officer_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Confirm this farmer user belongs to the officer's area. Returns the farmers row or null.
function own_farmer($db, $eoId, $userId) {
    $stmt = $db->prepare("SELECT f.* FROM farmers f WHERE f.user_id = ? AND f.assigned_extension_officer_id = ?");
    $stmt->execute([(int)$userId, $eoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($method === 'GET') {
    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId <= 0) jsonResponse(['error' => 'Missing farmer id'], 400);

    $stmt = $db->prepare("
        SELECT u.id AS user_id, u.name, u.email, u.phone, u.status, u.profile_photo, u.created_at,
               l.name AS location_name,
               f.farm_size, f.farm_size_unit, f.farming_type
        FROM users u
        JOIN farmers f ON f.user_id = u.id
        LEFT JOIN locations l ON l.id = f.location_id
        WHERE u.id = ? AND f.assigned_extension_officer_id = ?
    ");
    $stmt->execute([$userId, $eoId]);
    $farmer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$farmer) jsonResponse(['error' => 'Farmer not found or not assigned to you'], 404);

    // Cooperative membership (if any)
    $stmt = $db->prepare("
        SELECT c.id, c.name, cm.role
        FROM cooperative_members cm
        JOIN cooperatives c ON c.id = cm.cooperative_id
        WHERE cm.user_id = ?
        ORDER BY cm.joined_at DESC
    ");
    $stmt->execute([$userId]);
    $farmer['cooperatives'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(['success' => true, 'farmer' => $farmer]);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action !== 'photo') jsonResponse(['error' => 'Unsupported action'], 400);

    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) jsonResponse(['error' => 'Missing farmer id'], 400);
    if (!own_farmer($db, $eoId, $userId)) jsonResponse(['error' => 'Farmer not found or not assigned to you'], 404);

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No photo was uploaded'], 400);
    }
    $file = $_FILES['photo'];

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowed)) {
        jsonResponse(['error' => 'Only JPEG, PNG, WEBP or GIF images are allowed'], 400);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(['error' => 'Photo too large. Maximum size is 5MB.'], 400);
    }

    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $ext = $extMap[$mimeType] ?? 'jpg';
    $filename = 'profile_' . time() . '_' . uniqid() . '.' . $ext;

    $uploadDir = __DIR__ . '/../../uploads/images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        jsonResponse(['error' => 'Failed to save uploaded photo'], 500);
    }

    $relPath = 'images/' . $filename;
    $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?")->execute([$relPath, $userId]);

    jsonResponse(['success' => true, 'message' => 'Photo updated', 'profile_photo' => $relPath], 201);
}

jsonResponse(['error' => 'Method not allowed'], 405);
