<?php
/**
 * AgriConnect - Login API
 * POST /api/auth/login.php
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = getJsonInput();

if (empty($input['email']) || empty($input['password'])) {
    jsonResponse(['error' => 'Email and password are required'], 400);
}

$email = trim($input['email']);
$password = $input['password'];

$db = getDB();

// Find user by email
$stmt = $db->prepare("
    SELECT u.*, eo.id as extension_officer_id, eo.location_id as eo_location_id
    FROM users u
    LEFT JOIN extension_officers eo ON eo.user_id = u.id
    WHERE u.email = ?
");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(['error' => 'Invalid email or password'], 401);
}

if ($user['status'] !== 'active') {
    jsonResponse(['error' => 'Your account is ' . $user['status'] . '. Please contact the administrator.'], 403);
}

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    jsonResponse(['error' => 'Invalid email or password'], 401);
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['name'];

// Generate token
$token = generateToken($user['id']);

// Prepare response based on role
$response = [
    'success' => true,
    'message' => 'Login successful',
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'role' => $user['role'],
        'location_id' => $user['location_id'],
        'status' => $user['status'],
        'extension_officer_id' => $user['extension_officer_id']
    ],
    'redirect' => ''
];

// Set redirect based on role
switch ($user['role']) {
    case 'admin':
        $response['redirect'] = '../admin/dashboard.html';
        break;
    case 'extension_officer':
        $response['redirect'] = '../extension/dashboard.html';
        break;
    case 'farmer':
        $response['redirect'] = '../farmer/dashboard.html';
        break;
    case 'cooperative_leader':
        $response['redirect'] = '../cooperative/dashboard.html';
        break;
}

jsonResponse($response);
?>
