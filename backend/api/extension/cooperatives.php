<?php
/**
 * AgriConnect - Extension Officer Cooperatives API
 * GET  - List cooperatives managed by this officer (with members)
 * POST - Create a new cooperative (leader + optional members from the officer's farmers)
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$officer = requireRole('extension_officer');
$db = getDB();
$eoId = $officer['extension_officer_id'];

$method = $_SERVER['REQUEST_METHOD'];

// Helper: is this user a farmer belonging to (or visible to) this officer?
function coop_is_farmer($db, $userId) {
    $stmt = $db->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'farmer'");
    $stmt->execute([(int)$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($method === 'POST') {
    $input = getJsonInput();

    // ---- Add a member to an existing cooperative ----
    if (($input['action'] ?? '') === 'add_member') {
        $coopId = (int)($input['cooperative_id'] ?? 0);
        $role = $input['role'] ?? 'member';
        $allowedRoles = ['member', 'deputy', 'treasurer', 'secretary'];
        if (!in_array($role, $allowedRoles, true)) $role = 'member';

        // Cooperative must exist and belong to this officer
        $stmt = $db->prepare("SELECT id, name, location_id FROM cooperatives WHERE id = ? AND extension_officer_id = ?");
        $stmt->execute([$coopId, $eoId]);
        $coop = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coop) jsonResponse(['error' => 'Cooperative not found'], 404);

        $userId = (int)($input['user_id'] ?? 0);
        $isNew = false;
        $newName = trim($input['member_name'] ?? '');
        $newPhone = trim($input['member_phone'] ?? '');
        $notifyUser = true;

        if ($userId > 0) {
            // Existing registered farmer
            if (!coop_is_farmer($db, $userId)) jsonResponse(['error' => 'Please select a valid farmer'], 400);
        } elseif ($newName !== '') {
            // Manually-entered member (may be offline / no account)
            $isNew = true;
            $notifyUser = false;
        } else {
            jsonResponse(['error' => 'Select an existing farmer or enter a new member name'], 400);
        }

        // Not already a member (existing farmers only; new members always get a fresh id)
        if (!$isNew) {
            $stmt = $db->prepare("SELECT id FROM cooperative_members WHERE cooperative_id = ? AND user_id = ?");
            $stmt->execute([$coopId, $userId]);
            if ($stmt->fetch()) jsonResponse(['error' => 'That farmer is already a member of this cooperative'], 409);
        }

        try {
            $db->beginTransaction();

            if ($isNew) {
                // Create a lightweight, non-loginable user record for the offline member
                $email = 'member-' . bin2hex(random_bytes(6)) . '@coop.agriconnect.local';
                $pwHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $cleanPhone = $newPhone !== '' ? preg_replace('/[^0-9+]/', '', $newPhone) : null;
                $db->prepare("INSERT INTO users (name, email, phone, password_hash, role, location_id, status) VALUES (?, ?, ?, ?, 'farmer', ?, 'active')")
                   ->execute([$newName, $email, $cleanPhone, $pwHash, (int)$coop['location_id'] ?: null]);
                $userId = (int)$db->lastInsertId();

                // Register a farmer profile so the officer can see/manage this member too
                if ((int)$coop['location_id'] > 0) {
                    $db->prepare("INSERT INTO farmers (user_id, location_id, assigned_extension_officer_id) VALUES (?, ?, ?)")
                       ->execute([$userId, (int)$coop['location_id'], $eoId]);
                }
            }

            $db->prepare("INSERT INTO cooperative_members (cooperative_id, user_id, role) VALUES (?, ?, ?)")
               ->execute([$coopId, $userId, $role]);
            $cnt = $db->prepare("SELECT COUNT(*) FROM cooperative_members WHERE cooperative_id = ?");
            $cnt->execute([$coopId]);
            $db->prepare("UPDATE cooperatives SET members_count = ? WHERE id = ?")
               ->execute([(int)$cnt->fetchColumn(), $coopId]);

            if ($notifyUser) {
                try {
                    $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'system')")
                       ->execute([$userId, 'You were added to a cooperative',
                           'You have been added as ' . $role . ' of "' . $coop['name'] . '".']);
                } catch (Exception $ne) { /* non-critical */ }
            }
            $db->commit();
            jsonResponse(['success' => true, 'message' => $isNew ? 'New member added' : 'Member added']);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['error' => 'Failed to add member: ' . $e->getMessage()], 500);
        }
    }

    // ---- Create a new cooperative ----
    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '');
    $locationId = (int)($input['location_id'] ?? 0);
    $leaderId = (int)($input['leader_id'] ?? 0);
    $memberIds = $input['member_ids'] ?? [];
    if (!is_array($memberIds)) $memberIds = [];

    if ($name === '') jsonResponse(['error' => 'Cooperative name is required'], 400);

    // Location must exist
    $chk = $db->prepare("SELECT id FROM locations WHERE id = ?");
    $chk->execute([$locationId]);
    if (!$chk->fetch()) jsonResponse(['error' => 'Please select a valid location'], 400);

    // Leader must be a farmer
    $leader = coop_is_farmer($db, $leaderId);
    if (!$leader) jsonResponse(['error' => 'Please select a farmer as the cooperative leader'], 400);

    try {
        $db->beginTransaction();

        $ins = $db->prepare("
            INSERT INTO cooperatives (name, description, location_id, leader_id, extension_officer_id, members_count, status)
            VALUES (?, ?, ?, ?, ?, 0, 'active')
        ");
        $ins->execute([$name, $description ?: null, $locationId, $leaderId, $eoId]);
        $coopId = (int)$db->lastInsertId();

        // Build a clean, unique set of member user ids (leader first)
        $userIds = [$leaderId];
        foreach ($memberIds as $mid) {
            $mid = (int)$mid;
            if ($mid > 0 && $mid !== $leaderId && !in_array($mid, $userIds, true) && coop_is_farmer($db, $mid)) {
                $userIds[] = $mid;
            }
        }

        $memberIns = $db->prepare("INSERT INTO cooperative_members (cooperative_id, user_id, role) VALUES (?, ?, ?)");
        foreach ($userIds as $idx => $uid) {
            $memberIns->execute([$coopId, $uid, $idx === 0 ? 'leader' : 'member']);
        }

        $cnt = $db->prepare("UPDATE cooperatives SET members_count = ? WHERE id = ?");
        $cnt->execute([count($userIds), $coopId]);

        // Notify the leader
        try {
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'system')")
               ->execute([$leaderId, 'You were added to a cooperative',
                   'You have been registered as the leader of "' . $name . '".']);
        } catch (Exception $ne) { /* non-critical */ }

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Cooperative created', 'id' => $coopId], 201);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        jsonResponse(['error' => 'Failed to create cooperative: ' . $e->getMessage()], 500);
    }
}

// ---- GET: list ----
$stmt = $db->prepare("
    SELECT c.*, u.name as leader_name, u.email as leader_email, u.phone as leader_phone,
           l.name as location_name
    FROM cooperatives c
    JOIN users u ON u.id = c.leader_id
    LEFT JOIN locations l ON l.id = c.location_id
    WHERE c.extension_officer_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$eoId]);
$cooperatives = $stmt->fetchAll();

foreach ($cooperatives as &$coop) {
    $stmt = $db->prepare("
        SELECT cm.*, u.name, u.email, u.phone
        FROM cooperative_members cm
        JOIN users u ON u.id = cm.user_id
        WHERE cm.cooperative_id = ?
        ORDER BY cm.role ASC, cm.joined_at ASC
    ");
    $stmt->execute([$coop['id']]);
    $coop['members'] = $stmt->fetchAll();
}

jsonResponse(['success' => true, 'cooperatives' => $cooperatives]);
