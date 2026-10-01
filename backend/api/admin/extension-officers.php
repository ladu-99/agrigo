<?php
/**
 * AgriConnect - Extension Officers Management API
 * GET    - List all extension officers
 * POST   - Create a new extension officer
 * PUT    - Update an extension officer or toggle status
 * DELETE - Remove an extension officer
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$admin = requireRole('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // List all extension officers with details
        $stmt = $db->query("
            SELECT u.id, eo.user_id, u.name, u.email, u.phone, u.status,
                   u.location_id, eo.id as eo_id, eo.specialization_id,
                   l.name as location_name,
                   s.name as specialization_name
            FROM extension_officers eo
            JOIN users u ON u.id = eo.user_id
            LEFT JOIN locations l ON l.id = eo.location_id
            LEFT JOIN specializations s ON s.id = eo.specialization_id
            ORDER BY u.created_at DESC
        ");
        $officers = $stmt->fetchAll();

        jsonResponse(['success' => true, 'officers' => $officers]);
        break;

    case 'POST':
        // Create a new extension officer
        $input = getJsonInput();

        $required = ['name', 'email', 'location_id', 'password'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonResponse(['error' => "Field '$field' is required"], 400);
            }
        }

        if (strlen($input['password']) < 6) {
            jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
        }

        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([trim($input['email'])]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Email already exists'], 409);
        }

        try {
            $db->beginTransaction();

            // Create user
            $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO users (name, email, phone, password_hash, role, location_id, status)
                VALUES (?, ?, ?, ?, 'extension_officer', ?, 'active')
            ");
            $stmt->execute([
                trim($input['name']),
                trim(strtolower($input['email'])),
                trim($input['phone'] ?? ''),
                $passwordHash,
                (int)$input['location_id']
            ]);
            $userId = $db->lastInsertId();

            // Create extension officer record
            $specId = !empty($input['specialization_id']) ? (int)$input['specialization_id'] : null;
            $stmt = $db->prepare("
                INSERT INTO extension_officers (user_id, location_id, specialization_id, assigned_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, (int)$input['location_id'], $specId, $admin['id']]);

            $db->commit();
            jsonResponse(['success' => true, 'message' => 'Extension officer created successfully'], 201);

        } catch (PDOException $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Failed to create officer: ' . $e->getMessage()], 500);
        }
        break;

    case 'PUT':
        // Update extension officer
        $input = getJsonInput();

        if (!empty($input['user_id']) && !empty($input['status'])) {
            // Toggle status
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'extension_officer'");
            $stmt->execute([$input['status'], (int)$input['user_id']]);
            jsonResponse(['success' => true, 'message' => 'Status updated']);
        }

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Officer ID is required'], 400);
        }

        try {
            $db->beginTransaction();

            // Update user info
            $stmt = $db->prepare("
                UPDATE users SET name = ?, email = ?, phone = ?, location_id = ?
                WHERE id = (SELECT user_id FROM extension_officers WHERE id = ?)
            ");
            $stmt->execute([
                trim($input['name']),
                trim(strtolower($input['email'])),
                trim($input['phone'] ?? ''),
                (int)$input['location_id'],
                (int)$input['id']
            ]);

            // Update extension officer info
            $specId = !empty($input['specialization_id']) ? (int)$input['specialization_id'] : null;
            $stmt = $db->prepare("
                UPDATE extension_officers SET location_id = ?, specialization_id = ?
                WHERE id = ?
            ");
            $stmt->execute([(int)$input['location_id'], $specId, (int)$input['id']]);

            $db->commit();
            jsonResponse(['success' => true, 'message' => 'Officer updated successfully']);

        } catch (PDOException $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Failed to update officer: ' . $e->getMessage()], 500);
        }
        break;

    case 'DELETE':
        $input = getJsonInput();
        if (empty($input['id'])) {
            jsonResponse(['error' => 'Officer ID is required'], 400);
        }

        $stmt = $db->prepare("SELECT user_id FROM extension_officers WHERE id = ?");
        $stmt->execute([(int)$input['id']]);
        $officer = $stmt->fetch();

        if (!$officer) {
            jsonResponse(['error' => 'Officer not found'], 404);
        }

        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'users', (int)$officer['user_id'], 'Extension Officer');

        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$officer['user_id']]);

        jsonResponse(['success' => true, 'message' => 'Officer deleted']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
