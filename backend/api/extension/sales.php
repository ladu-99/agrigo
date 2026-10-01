<?php
/**
 * AgriConnect - Extension Officer Sales Records API
 * Extension officers record and track sales from marketplace listings.
 * GET    - View sales history with summary stats (supports ?listing_id= filter)
 * POST   - Record a new sale (reduces listing quantity, updates status if fully sold)
 * DELETE - Remove a sale record (restores listing quantity)
 */

require_once __DIR__ . '/../../middleware/auth.php';

setCorsHeaders();
$officer = requireRole('extension_officer');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$eoId = $officer['extension_officer_id'];

switch ($method) {
    case 'GET':
        $listingFilter = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : null;

        // Summary stats
        $summarySql = "
            SELECT
                COUNT(*) as total_sales,
                COALESCE(SUM(ms.quantity_sold), 0) as total_quantity_sold,
                COALESCE(SUM(ms.total_amount), 0) as total_revenue
            FROM marketplace_sales ms
            WHERE ms.extension_officer_id = ?
        ";
        $summaryParams = [$eoId];

        if ($listingFilter) {
            $summarySql .= " AND ms.listing_id = ?";
            $summaryParams[] = $listingFilter;
        }

        $stmt = $db->prepare($summarySql);
        $stmt->execute($summaryParams);
        $summary = $stmt->fetch();

        // Sales list with product details
        $salesSql = "
            SELECT ms.*, ml.product_name, ml.quantity_unit, ml.product_photo,
                   ml.quantity as listing_quantity, ml.status as listing_status
            FROM marketplace_sales ms
            JOIN market_listings ml ON ml.id = ms.listing_id
            WHERE ms.extension_officer_id = ?
        ";
        $salesParams = [$eoId];

        if ($listingFilter) {
            $salesSql .= " AND ms.listing_id = ?";
            $salesParams[] = $listingFilter;
        }

        $salesSql .= " ORDER BY ms.sale_date DESC, ms.created_at DESC";

        $stmt = $db->prepare($salesSql);
        $stmt->execute($salesParams);
        $sales = $stmt->fetchAll();

        // Per-product breakdown
        $breakdownSql = "
            SELECT
                ml.id as listing_id,
                ml.product_name,
                ml.quantity_unit,
                ml.price_ssp as listing_price,
                ml.quantity as remaining_quantity,
                ml.status as listing_status,
                ml.product_photo,
                COALESCE(SUM(ms.quantity_sold), 0) as total_sold,
                COALESCE(SUM(ms.total_amount), 0) as product_revenue,
                COUNT(ms.id) as sale_count
            FROM market_listings ml
            LEFT JOIN marketplace_sales ms ON ms.listing_id = ml.id AND ms.extension_officer_id = ?
            WHERE ml.added_by = ?
            GROUP BY ml.id
            ORDER BY product_revenue DESC
        ";
        $stmt = $db->prepare($breakdownSql);
        $stmt->execute([$eoId, $eoId]);
        $breakdown = $stmt->fetchAll();

        jsonResponse([
            'success' => true,
            'summary' => [
                'total_sales' => (int)$summary['total_sales'],
                'total_quantity_sold' => (float)$summary['total_quantity_sold'],
                'total_revenue' => (float)$summary['total_revenue']
            ],
            'sales' => $sales,
            'breakdown' => $breakdown
        ]);
        break;

    case 'POST':
        $input = getJsonInput();

        $listingId = (int)($input['listing_id'] ?? 0);
        $buyerName = trim($input['buyer_name'] ?? '');
        $buyerContact = trim($input['buyer_contact'] ?? '');
        $quantitySold = (float)($input['quantity_sold'] ?? 0);
        $salePrice = (float)($input['sale_price'] ?? 0);
        $notes = trim($input['notes'] ?? '');
        $saleDate = $input['sale_date'] ?? date('Y-m-d');

        // Validate
        if ($listingId <= 0) {
            jsonResponse(['error' => 'Product listing is required'], 400);
        }
        if ($quantitySold <= 0) {
            jsonResponse(['error' => 'Quantity sold must be greater than zero'], 400);
        }
        if ($salePrice <= 0) {
            jsonResponse(['error' => 'Sale price must be greater than zero'], 400);
        }

        // Verify listing belongs to this officer
        $stmt = $db->prepare("SELECT * FROM market_listings WHERE id = ? AND added_by = ?");
        $stmt->execute([$listingId, $eoId]);
        $listing = $stmt->fetch();

        if (!$listing) {
            jsonResponse(['error' => 'Product listing not found or does not belong to you'], 404);
        }

        // Check available quantity
        if ($quantitySold > (float)$listing['quantity']) {
            jsonResponse(['error' => 'Quantity sold exceeds available quantity (' . $listing['quantity'] . ' ' . $listing['quantity_unit'] . ' available)'], 400);
        }

        $totalAmount = $quantitySold * $salePrice;

        // Record the sale
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO marketplace_sales
                (listing_id, extension_officer_id, buyer_name, buyer_contact, quantity_sold, sale_price, total_amount, notes, sale_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $listingId, $eoId,
                $buyerName ?: null,
                $buyerContact ?: null,
                $quantitySold, $salePrice, $totalAmount,
                $notes ?: null, $saleDate
            ]);

            $saleId = $db->lastInsertId();

            // Reduce listing quantity
            $newQuantity = (float)$listing['quantity'] - $quantitySold;
            $newStatus = $newQuantity <= 0 ? 'sold' : $listing['status'];

            $stmt = $db->prepare("UPDATE market_listings SET quantity = ?, status = ? WHERE id = ?");
            $stmt->execute([$newQuantity, $newStatus, $listingId]);

            $db->commit();

            jsonResponse([
                'success' => true,
                'id' => $saleId,
                'total_amount' => $totalAmount,
                'remaining_quantity' => $newQuantity,
                'message' => 'Sale recorded successfully'
            ], 201);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Failed to record sale. Please try again.'], 500);
        }
        break;

    case 'DELETE':
        $input = getJsonInput();
        $saleId = (int)($input['id'] ?? 0);

        if ($saleId <= 0) {
            jsonResponse(['error' => 'Sale record ID is required'], 400);
        }

        // Verify sale belongs to this officer
        $stmt = $db->prepare("SELECT * FROM marketplace_sales WHERE id = ? AND extension_officer_id = ?");
        $stmt->execute([$saleId, $eoId]);
        $sale = $stmt->fetch();

        if (!$sale) {
            jsonResponse(['error' => 'Sale record not found'], 404);
        }

        $db->beginTransaction();
        try {
            // Restore quantity to listing
            $stmt = $db->prepare("SELECT quantity, status FROM market_listings WHERE id = ?");
            $stmt->execute([$sale['listing_id']]);
            $listing = $stmt->fetch();

            $restoredQty = (float)$listing['quantity'] + (float)$sale['quantity_sold'];
            $restoredStatus = $listing['status'] === 'sold' ? 'active' : $listing['status'];

            $stmt = $db->prepare("UPDATE market_listings SET quantity = ?, status = ? WHERE id = ?");
            $stmt->execute([$restoredQty, $restoredStatus, $sale['listing_id']]);

            // Delete the sale record
            require_once __DIR__ . '/../../models/trash.php';
            trash_store($db, 'marketplace_sales', $saleId, 'Sale Record');

            $stmt = $db->prepare("DELETE FROM marketplace_sales WHERE id = ?");
            $stmt->execute([$saleId]);

            $db->commit();
            jsonResponse(['success' => true, 'message' => 'Sale record removed, quantity restored']);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Failed to remove sale record.'], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
