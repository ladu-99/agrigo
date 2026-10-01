<?php
/**
 * AgriConnect - Registration API
 * POST /api/auth/register.php
 * Supports farmer and cooperative_leader registration
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = getJsonInput();

// Validate required fields
$required = ['name', 'email', 'phone', 'password', 'role', 'location_id'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        jsonResponse(['error' => "Field '$field' is required"], 400);
    }
}

$name = trim($input['name']);
$email = trim(strtolower($input['email']));
$phone = trim($input['phone']);
$password = $input['password'];
$role = $input['role'];
$locationId = (int)$input['location_id'];

// Validate role - only farmers and cooperative leaders can self-register
if (!in_array($role, ['farmer', 'cooperative_leader'])) {
    jsonResponse(['error' => 'Self-registration is only available for farmers and cooperative leaders'], 400);
}

// Validate password length
if (strlen($password) < 6) {
    jsonResponse(['error' => 'Password must be at least 6 characters long'], 400);
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Invalid email format'], 400);
}

$db = getDB();

// Check if email already exists
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'An account with this email already exists'], 409);
}

// Hash password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $db->beginTransaction();

    // Create user
    $stmt = $db->prepare("
        INSERT INTO users (name, email, phone, password_hash, role, location_id, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$name, $email, $phone, $passwordHash, $role, $locationId]);
    $userId = $db->lastInsertId();

    // Create role-specific record
    if ($role === 'farmer') {
        $farmSize = !empty($input['farm_size']) ? (float)$input['farm_size'] : null;
        $farmSizeUnit = !empty($input['farm_size_unit']) ? $input['farm_size_unit'] : 'acres';
        $farmingType = !empty($input['farming_type']) ? trim($input['farming_type']) : null;

        // Find extension officer for this location
        $stmt = $db->prepare("
            SELECT eo.id FROM extension_officers eo
            WHERE eo.location_id = ?
            ORDER BY eo.id ASC LIMIT 1
        ");
        $stmt->execute([$locationId]);
        $eo = $stmt->fetch();
        $eoId = $eo ? $eo['id'] : null;

        $stmt = $db->prepare("
            INSERT INTO farmers (user_id, location_id, farm_size, farm_size_unit, farming_type, assigned_extension_officer_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $locationId, $farmSize, $farmSizeUnit, $farmingType, $eoId]);

    } elseif ($role === 'cooperative_leader') {
        $coopName = !empty($input['cooperative_name']) ? trim($input['cooperative_name']) : null;
        $coopDescription = !empty($input['cooperative_description']) ? trim($input['cooperative_description']) : null;

        if ($coopName) {
            // Find extension officer for this location
            $stmt = $db->prepare("
                SELECT eo.id FROM extension_officers eo
                WHERE eo.location_id = ?
                ORDER BY eo.id ASC LIMIT 1
            ");
            $stmt->execute([$locationId]);
            $eo = $stmt->fetch();
            $eoId = $eo ? $eo['id'] : null;

            // Create cooperative
            $stmt = $db->prepare("
                INSERT INTO cooperatives (name, description, location_id, leader_id, extension_officer_id, members_count)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$coopName, $coopDescription, $locationId, $userId, $eoId]);
            $coopId = $db->lastInsertId();

            // Add leader as member
            $stmt = $db->prepare("
                INSERT INTO cooperative_members (cooperative_id, user_id, role)
                VALUES (?, ?, 'leader')
            ");
            $stmt->execute([$coopId, $userId]);
        }
    }

    $db->commit();

    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $role;
    $_SESSION['user_name'] = $name;

    $token = generateToken($userId);

    $redirect = ($role === 'farmer') ? '../farmer/dashboard.html' : '../cooperative/dashboard.html';

    jsonResponse([
        'success' => true,
        'message' => 'Registration successful',
        'token' => $token,
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'location_id' => $locationId
        ],
        'redirect' => $redirect
    ], 201);

} catch (PDOException $e) {
    $db->rollBack();
    jsonResponse(['error' => 'Registration failed: ' . $e->getMessage()], 500);
}
?>
