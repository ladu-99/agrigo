<?php
/**
 * AgriConnect - Extension Officer Farmer Approval API
 * POST - Approve or reject a pending farmer registration assigned to this officer.
 *        Body: { user_id: int, action: 'approve' | 'reject' }
 *
 * Approving activates the farmer's account (status -> active) so they can sign in
 * and receive resources and notifications. Rejecting deactivates it.
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$officer = requireRole('extension_officer');
$db = getDB();
$eoId = $officer['extension_officer_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = getJsonInput();
$farmerUserId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$action = $input['action'] ?? '';

if (!$farmerUserId || !in_array($action, ['approve', 'reject'], true)) {
    jsonResponse(['error' => 'A valid user_id and action (approve/reject) are required'], 400);
}

// Verify the farmer exists, is a farmer, and is assigned to this officer.
$stmt = $db->prepare("
    SELECT u.id, u.status
    FROM users u
    JOIN farmers f ON f.user_id = u.id
    WHERE u.id = ? AND f.assigned_extension_officer_id = ? AND u.role = 'farmer'
");
$stmt->execute([$farmerUserId, $eoId]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['error' => 'Farmer not found or not assigned to you'], 404);
}

if ($action === 'approve') {
    $db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$farmerUserId]);
    $db->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        VALUES (?, 'Account approved', 'Your AgriConnect account has been approved. You can now sign in to access resources and announcements.', 'system')
    ")->execute([$farmerUserId]);

    jsonResponse(['success' => true, 'message' => 'Farmer approved. They can now sign in.']);
}

// reject
$db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$farmerUserId]);
$db->prepare("
    INSERT INTO notifications (user_id, title, message, type)
    VALUES (?, 'Registration update', 'Your AgriConnect registration was not approved. Please contact your extension officer for details.', 'system')
")->execute([$farmerUserId]);

jsonResponse(['success' => true, 'message' => 'Farmer registration rejected.']);
?>
