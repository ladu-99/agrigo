<?php
/**
 * AgriConnect - Public Contact Message API
 * Visitors on the public site send a direct message to the admin.
 * No authentication required. POST only.
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = getJsonInput();
if (!is_array($input)) {
    $input = $_POST;
}

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');

if ($name === '' || $message === '') {
    jsonResponse(['error' => 'Your name and message are required'], 400);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Please enter a valid email address'], 400);
}

if (mb_strlen($name) > 255 || mb_strlen($message) > 10000) {
    jsonResponse(['error' => 'One of the fields is too long'], 400);
}

$stmt = $db->prepare("
    INSERT INTO contact_messages (name, email, phone, subject, message)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([
    $name,
    $email !== '' ? $email : null,
    $phone !== '' ? $phone : null,
    $subject !== '' ? $subject : null,
    $message
]);

// Notify admin users there is a new message.
$admins = $db->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
if ($admins) {
    $nstmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'system', 0)");
    foreach ($admins as $adminId) {
        $nstmt->execute([$adminId, 'New contact message', $name . ': ' . substr($message, 0, 140)]);
    }
}

jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Thank you for your message! We will get back to you soon.'], 201);
