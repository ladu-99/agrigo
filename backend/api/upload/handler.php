<?php
/**
 * AgriConnect - Universal File Upload Handler
 * POST - Upload files (images, videos, documents, product photos)
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$user = authenticate();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$category = isset($_POST['category']) ? $_POST['category'] : 'general';
$allowedCategories = ['resources', 'images', 'documents', 'products'];

if (!in_array($category, $allowedCategories)) {
    jsonResponse(['error' => 'Invalid upload category'], 400);
}

$uploadDir = __DIR__ . '/../../uploads/' . $category . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Define allowed file types per category
$allowedTypes = [
    'resources' => ['video/mp4', 'video/avi', 'video/mov', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'images'    => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    'documents' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'products'  => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    'general'   => ['image/jpeg', 'image/png', 'application/pdf', 'application/msword']
];

$maxSizes = [
    'resources' => 50 * 1024 * 1024,  // 50MB for videos
    'images'    => 5 * 1024 * 1024,    // 5MB for images
    'documents' => 10 * 1024 * 1024,   // 10MB for documents
    'products'  => 5 * 1024 * 1024,    // 5MB for product photos
    'general'   => 10 * 1024 * 1024
];

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory missing',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension'
    ];
    $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    jsonResponse(['error' => $errorMessages[$errCode] ?? 'Upload failed'], 400);
}

$file = $_FILES['file'];

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = $allowedTypes[$category] ?? $allowedTypes['general'];
if (!in_array($mimeType, $allowed)) {
    jsonResponse(['error' => 'File type not allowed for this category. Allowed: ' . implode(', ', $allowed)], 400);
}

// Validate file size
$maxSize = $maxSizes[$category] ?? $maxSizes['general'];
if ($file['size'] > $maxSize) {
    $maxMB = round($maxSize / (1024 * 1024));
    jsonResponse(['error' => "File too large. Maximum size is {$maxMB}MB for this category."], 400);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $category . '_' . time() . '_' . uniqid() . '.' . strtolower($ext);
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    jsonResponse(['error' => 'Failed to save uploaded file'], 500);
}

$filePath = $category . '/' . $filename;

jsonResponse([
    'success' => true,
    'message' => 'File uploaded successfully',
    'file_path' => $filePath,
    'file_name' => $filename,
    'file_size' => $file['size'],
    'mime_type' => $mimeType
], 201);
?>
