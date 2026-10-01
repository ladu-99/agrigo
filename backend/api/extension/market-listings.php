<?php
/**
 * AgriConnect - Extension Officer Market Listings API
 * Extension officers add agricultural products with:
 *   - Product name
 *   - Quantity and unit
 *   - Price in SSP (South Sudanese Pound)
 *   - Product photo
 * GET    - List all market listings by this officer
 * POST   - Add a new agricultural product listing
 * PUT    - Update a listing
 * DELETE - Remove a listing
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
            SELECT ml.*, l.name as location_name
            FROM market_listings ml
            LEFT JOIN locations l ON l.id = ml.location_id
            WHERE ml.added_by = ?
            ORDER BY ml.created_at DESC
        ");
        $stmt->execute([$eoId]);
        $listings = $stmt->fetchAll();

        jsonResponse(['success' => true, 'listings' => $listings]);
        break;

    case 'POST':
        // Support both JSON and multipart form data
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

        if (strpos($contentType, 'multipart/form-data') !== false) {
            // Handle file upload
            $productName = trim($_POST['product_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $quantity = (float)($_POST['quantity'] ?? 0);
            $quantityUnit = $_POST['quantity_unit'] ?? 'kg';
            $priceSsp = (float)($_POST['price_ssp'] ?? 0);
            $productType = $_POST['product_type'] ?? 'crop';
            $sellerType = $_POST['seller_type'] ?? 'farmer';
            $sellerId = !empty($_POST['seller_id']) ? (int)$_POST['seller_id'] : null;
            $locationId = (int)($_POST['location_id'] ?? $officer['location_id']);

            // Validate
            if (empty($productName) || $quantity <= 0 || $priceSsp <= 0) {
                jsonResponse(['error' => 'Product name, quantity, and price in SSP are required'], 400);
            }

            // Handle product photo upload
            $photoPath = null;
            if (isset($_FILES['product_photo']) && $_FILES['product_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../uploads/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $file = $_FILES['product_photo'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mimeType, $allowedTypes)) {
                    jsonResponse(['error' => 'Only JPG, PNG, GIF, and WebP images are allowed for product photos'], 400);
                }

                $maxSize = 5 * 1024 * 1024; // 5MB
                if ($file['size'] > $maxSize) {
                    jsonResponse(['error' => 'Product photo must be less than 5MB'], 400);
                }

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'product_' . time() . '_' . uniqid() . '.' . $ext;
                $destination = $uploadDir . $filename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    jsonResponse(['error' => 'Failed to upload product photo'], 500);
                }

                $photoPath = 'products/' . $filename;
            }

            $stmt = $db->prepare("
                INSERT INTO market_listings
                (product_name, description, quantity, quantity_unit, price_ssp, product_photo, product_type, seller_type, seller_id, location_id, added_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $productName,
                $description,
                $quantity,
                $quantityUnit,
                $priceSsp,
                $photoPath,
                $productType,
                $sellerType,
                $sellerId,
                $locationId,
                $eoId
            ]);

            jsonResponse([
                'success' => true,
                'id' => $db->lastInsertId(),
                'product_photo' => $photoPath,
                'message' => 'Product listing added successfully'
            ], 201);

        } else {
            // JSON input
            $input = getJsonInput();

            $productName = trim($input['product_name'] ?? '');
            $description = trim($input['description'] ?? '');
            $quantity = (float)($input['quantity'] ?? 0);
            $quantityUnit = $input['quantity_unit'] ?? 'kg';
            $priceSsp = (float)($input['price_ssp'] ?? 0);
            $productType = $input['product_type'] ?? 'crop';
            $sellerType = $input['seller_type'] ?? 'farmer';
            $sellerId = !empty($input['seller_id']) ? (int)$input['seller_id'] : null;
            $locationId = (int)($input['location_id'] ?? $officer['location_id']);
            $photoPath = $input['product_photo'] ?? null;

            if (empty($productName) || $quantity <= 0 || $priceSsp <= 0) {
                jsonResponse(['error' => 'Product name, quantity, and price in SSP are required'], 400);
            }

            $stmt = $db->prepare("
                INSERT INTO market_listings
                (product_name, description, quantity, quantity_unit, price_ssp, product_photo, product_type, seller_type, seller_id, location_id, added_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $productName,
                $description,
                $quantity,
                $quantityUnit,
                $priceSsp,
                $photoPath,
                $productType,
                $sellerType,
                $sellerId,
                $locationId,
                $eoId
            ]);

            jsonResponse([
                'success' => true,
                'id' => $db->lastInsertId(),
                'message' => 'Product listing added successfully'
            ], 201);
        }
        break;

    case 'PUT':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Listing ID is required'], 400);
        }

        $sets = [];
        $params = [];

        if (!empty($input['product_name'])) {
            $sets[] = 'product_name = ?';
            $params[] = trim($input['product_name']);
        }
        if (isset($input['description'])) {
            $sets[] = 'description = ?';
            $params[] = trim($input['description']);
        }
        if (!empty($input['quantity'])) {
            $sets[] = 'quantity = ?';
            $params[] = (float)$input['quantity'];
        }
        if (!empty($input['quantity_unit'])) {
            $sets[] = 'quantity_unit = ?';
            $params[] = $input['quantity_unit'];
        }
        if (!empty($input['price_ssp'])) {
            $sets[] = 'price_ssp = ?';
            $params[] = (float)$input['price_ssp'];
        }
        if (!empty($input['status'])) {
            $sets[] = 'status = ?';
            $params[] = $input['status'];
        }
        if (!empty($input['product_photo'])) {
            $sets[] = 'product_photo = ?';
            $params[] = $input['product_photo'];
        }

        if (empty($sets)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }

        $params[] = (int)$input['id'];
        $params[] = $eoId;

        $sql = "UPDATE market_listings SET " . implode(', ', $sets) . " WHERE id = ? AND added_by = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['success' => true, 'message' => 'Listing updated']);
        break;

    case 'DELETE':
        $input = getJsonInput();

        if (empty($input['id'])) {
            jsonResponse(['error' => 'Listing ID is required'], 400);
        }

        require_once __DIR__ . '/../../models/trash.php';
        trash_store($db, 'market_listings', (int)$input['id'], 'Market Listing');

        $stmt = $db->prepare("DELETE FROM market_listings WHERE id = ? AND added_by = ?");
        $stmt->execute([(int)$input['id'], $eoId]);

        jsonResponse(['success' => true, 'message' => 'Listing removed']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
