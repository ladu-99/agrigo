<?php
/**
 * AgriConnect - Extension Officer Sales Records (PHP Session + Server-Rendered)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$officer = requireLogin(['extension_officer']);
$eoId = $officer['extension_officer_id'];
$db = getDB();

// Server-render summary stats
$stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(quantity_sold),0) as qty, COALESCE(SUM(total_amount),0) as rev FROM marketplace_sales WHERE extension_officer_id = ?");
$stmt->execute([$eoId]);
$salesSummary = $stmt->fetch(PDO::FETCH_ASSOC);

// Server-render product breakdown
$stmt = $db->prepare("
    SELECT ml.id as listing_id, ml.product_name, ml.quantity_unit, ml.price_ssp as listing_price,
           ml.quantity as remaining_quantity, ml.status as listing_status,
           COALESCE(SUM(ms.quantity_sold), 0) as total_sold,
           COALESCE(SUM(ms.total_amount), 0) as product_revenue,
           COUNT(ms.id) as sale_count
    FROM market_listings ml
    LEFT JOIN marketplace_sales ms ON ms.listing_id = ml.id AND ms.extension_officer_id = ?
    WHERE ml.added_by = ?
    GROUP BY ml.id
    HAVING sale_count > 0
    ORDER BY product_revenue DESC
");
$stmt->execute([$eoId, $eoId]);
$breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Server-render sales history
$stmt = $db->prepare("
    SELECT ms.*, ml.product_name, ml.quantity_unit
    FROM marketplace_sales ms
    JOIN market_listings ml ON ml.id = ms.listing_id
    WHERE ms.extension_officer_id = ?
    ORDER BY ms.sale_date DESC, ms.created_at DESC
");
$stmt->execute([$eoId]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$productsSold = count($breakdown);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Sales Records</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Extension Officer Portal</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="resources.php"><span class="nav-icon">&#9654;</span> Resources</a>
                <a href="announcements.php"><span class="nav-icon">&#9993;</span> Announcements</a>
                <a href="farmers.php"><span class="nav-icon">&#9787;</span> Farmers</a>
                <a href="cooperatives.php"><span class="nav-icon">&#9881;</span> Cooperatives</a>
                <a href="marketplace.php"><span class="nav-icon">&#9733;</span> Marketplace</a>
                <a href="sales.php" class="active"><span class="nav-icon">&#9830;</span> Sales Records</a>
                                <a href="inquiries.php"><span class="nav-icon">&#9990;</span> Inquiries</a>
                                                <a href="reports.php"><span class="nav-icon">&#9998;</span> Reports</a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($officer['name']); ?></div>
                <div class="user-role">Extension Officer</div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">Sales Records</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($officer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Summary Stats (Server-Rendered) -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">&#9830;</div>
                        <div class="stat-info"><h4><?php echo $salesSummary['c']; ?></h4><p>Total Sales</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">&#9733;</div>
                        <div class="stat-info"><h4><?php echo $salesSummary['qty']; ?></h4><p>Total Quantity Sold</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">&#9733;</div>
                        <div class="stat-info"><h4><?php echo formatSSP($salesSummary['rev']); ?></h4><p>Total Revenue (SSP)</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red">&#9733;</div>
                        <div class="stat-info"><h4><?php echo $productsSold; ?></h4><p>Products Sold</p></div>
                    </div>
                </div>

                <!-- Product Breakdown (Server-Rendered) -->
                <div class="card">
                    <div class="card-header"><h3>Sales by Product</h3></div>
                    <?php if (empty($breakdown)): ?>
                        <div class="empty-state"><div class="empty-icon">&#9830;</div><p>No sales recorded yet. Record sales from the <a href="marketplace.php">Marketplace</a> page.</p></div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead><tr><th>Product</th><th>Qty Sold</th><th>Remaining</th><th>Revenue (SSP)</th><th>Sales</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($breakdown as $b):
                                $statusClass = $b['listing_status'] === 'active' ? 'badge-success' : 'badge-warning';
                            ?>
                                <tr>
                                    <td><strong><?php echo e($b['product_name']); ?></strong></td>
                                    <td><?php echo $b['total_sold']; ?> <?php echo e($b['quantity_unit']); ?></td>
                                    <td><?php echo $b['remaining_quantity']; ?> <?php echo e($b['quantity_unit']); ?></td>
                                    <td><strong><?php echo formatSSP($b['product_revenue']); ?></strong></td>
                                    <td><?php echo $b['sale_count']; ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo e($b['listing_status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Sales History (Server-Rendered) -->
                <div class="card" style="margin-top:1.5rem;">
                    <div class="card-header"><h3>Sales History</h3></div>
                    <?php if (empty($sales)): ?>
                        <div class="empty-state"><p>No sales records found.</p></div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead><tr><th>Date</th><th>Product</th><th>Qty Sold</th><th>Price/Unit (SSP)</th><th>Total (SSP)</th><th>Buyer</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($sales as $s): ?>
                                <tr>
                                    <td><?php echo formatDate($s['sale_date']); ?></td>
                                    <td><?php echo e($s['product_name']); ?></td>
                                    <td><?php echo $s['quantity_sold']; ?> <?php echo e($s['quantity_unit']); ?></td>
                                    <td><?php echo formatSSP($s['sale_price']); ?></td>
                                    <td><strong><?php echo formatSSP($s['total_amount']); ?></strong></td>
                                    <td><?php echo e($s['buyer_name'] ?? 'Walk-in'); ?></td>
                                    <td><button class="btn btn-sm btn-danger" onclick="deleteSale(<?php echo $s['id']; ?>)">Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        async function deleteSale(id) {
            if (!confirm('Remove this sale record? The product quantity will be restored.')) return;
            const data = await API.request('../../backend/api/extension/sales.php', {
                method: 'DELETE',
                body: JSON.stringify({ id: id })
            });
            if (data && data.success) {
                UI.showAlert('Sale record removed. Quantity restored.', 'success');
                window.location.reload();
            } else {
                UI.showAlert(data.error || 'Failed to remove sale.', 'danger');
            }
        }
    </script>
</body>
</html>
