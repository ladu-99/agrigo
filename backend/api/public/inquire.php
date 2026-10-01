<?php
/**
 * AgriConnect - Public Product Inquiry API
 * Allows a buyer on the public marketplace to send a request / inquiry
 * to buy a product. No authentication required. The extension officer
 * who listed the product receives the inquiry in their portal and can
 * contact the buyer by call / WhatsApp.
 * POST - Submit a product inquiry.
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Accept JSON or form-encoded input.
$input = getJsonInput();
if (!is_array($input)) {
    $input = $_POST;
}

$listingId = (int)($input['listing_id'] ?? 0);
$buyerName = trim($input['buyer_name'] ?? '');
$buyerPhone = trim($input['buyer_phone'] ?? '');
$buyerEmail = trim($input['buyer_email'] ?? '');
$quantityWanted = ($input['quantity_wanted'] ?? '') !== '' ? (float)$input['quantity_wanted'] : null;
$message = trim($input['message'] ?? '');

if ($listingId <= 0 || $buyerName === '' || $buyerPhone === '') {
    jsonResponse(['error' => 'Product, your name, and a contact number are required'], 400);
}

if (mb_strlen($buyerName) > 255) {
    jsonResponse(['error' => 'Name is too long'], 400);
}

// Normalize the phone to digits for validation (keep original for display).
$phoneDigits = preg_replace('/[^0-9]/', '', $buyerPhone);
if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
    jsonResponse(['error' => 'Please enter a valid phone number'], 400);
}

if ($buyerEmail !== '' && !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Please enter a valid email address'], 400);
}

// The product must exist and still be active.
$stmt = $db->prepare("SELECT id, product_name, status, added_by FROM market_listings WHERE id = ?");
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

if (!$listing || $listing['status'] !== 'active') {
    jsonResponse(['error' => 'This product is no longer available'], 404);
}

$stmt = $db->prepare("
    INSERT INTO product_inquiries
    (listing_id, buyer_name, buyer_phone, buyer_email, quantity_wanted, message)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $listingId,
    $buyerName,
    $buyerPhone,
    $buyerEmail !== '' ? $buyerEmail : null,
    $quantityWanted,
    $message !== '' ? $message : null
]);
$inquiryId = $db->lastInsertId();

// Notify the officer who owns the listing so they see it in-app too.
$stmt = $db->prepare("
    SELECT eo.user_id
    FROM extension_officers eo
    WHERE eo.id = ?
");
$stmt->execute([(int)$listing['added_by']]);
$officerUser = $stmt->fetch();

if ($officerUser) {
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, is_read)
        VALUES (?, ?, ?, 'market', 0)
    ");
    $stmt->execute([
        $officerUser['user_id'],
        'New buyer inquiry',
        $buyerName . ' is interested in "' . $listing['product_name'] . '". Open Marketplace Inquiries to call or WhatsApp them.'
    ]);
}

jsonResponse([
    'success' => true,
    'id' => $inquiryId,
    'message' => 'Your inquiry has been sent. The seller will contact you shortly.'
], 201);
