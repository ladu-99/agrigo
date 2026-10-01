<?php
/**
 * AgriConnect - Extension Officer Dashboard (Server-Rendered)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$officer = requireLogin(['extension_officer']);
$eoId = $officer['extension_officer_id'];
$db = getDB();

// Server-render stats
$stmt = $db->prepare("SELECT COUNT(*) as c FROM farmers WHERE assigned_extension_officer_id = ?");
$stmt->execute([$eoId]);
$statFarmers = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COUNT(*) as c FROM resources WHERE uploaded_by = ?");
$stmt->execute([$eoId]);
$statResources = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COUNT(*) as c FROM market_listings WHERE added_by = ? AND status = 'active'");
$stmt->execute([$eoId]);
$statListings = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COUNT(*) as c FROM cooperatives WHERE extension_officer_id = ? AND status = 'active'");
$stmt->execute([$eoId]);
$statCooperatives = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as rev FROM marketplace_sales WHERE extension_officer_id = ?");
$stmt->execute([$eoId]);
$salesRow = $stmt->fetch();
$statSales = $salesRow['c'];
$statRevenue = $salesRow['rev'];

// Recent announcements
$stmt = $db->prepare("SELECT id, title, priority, created_at FROM announcements WHERE created_by = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$eoId]);
$recentAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent resources
$stmt = $db->prepare("SELECT id, title, type, category, created_at FROM resources WHERE uploaded_by = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$eoId]);
$recentResources = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Extension Officer Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Extension Officer Portal</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="resources.php"><span class="nav-icon">&#9654;</span> Resources</a>
                <a href="announcements.php"><span class="nav-icon">&#9993;</span> Announcements</a>
                <a href="farmers.php"><span class="nav-icon">&#9787;</span> Farmers</a>
                <a href="cooperatives.php"><span class="nav-icon">&#9881;</span> Cooperatives</a>
                <a href="marketplace.php"><span class="nav-icon">&#9733;</span> Marketplace</a>
                <a href="sales.php"><span class="nav-icon">&#9830;</span> Sales Records</a>
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
                <h1 class="page-title">Dashboard</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($officer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">&#9787;</div>
                        <div class="stat-info"><h4><?php echo $statFarmers; ?></h4><p>Assigned Farmers</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">&#9654;</div>
                        <div class="stat-info"><h4><?php echo $statResources; ?></h4><p>Resources Uploaded</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">&#9733;</div>
                        <div class="stat-info"><h4><?php echo $statListings; ?></h4><p>Market Listings</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red">&#9881;</div>
                        <div class="stat-info"><h4><?php echo $statCooperatives; ?></h4><p>Cooperatives</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">&#9830;</div>
                        <div class="stat-info"><h4><?php echo $statSales; ?></h4><p>Total Sales</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">&#9733;</div>
                        <div class="stat-info"><h4><?php echo formatSSP($statRevenue); ?></h4><p>Revenue (SSP)</p></div>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="card">
                        <div class="card-header">
                            <h3>Recent Announcements</h3>
                            <a href="announcements.php" class="btn btn-outline btn-sm">View All</a>
                        </div>
                        <?php if (empty($recentAnnouncements)): ?>
                            <div class="empty-state"><p>No announcements yet.</p></div>
                        <?php else: ?>
                            <?php foreach ($recentAnnouncements as $a):
                                $pClass = $a['priority'] === 'urgent' ? 'badge-danger' : ($a['priority'] === 'high' ? 'badge-warning' : 'badge-info');
                            ?>
                                <div style="padding:0.6rem 0; border-bottom:1px solid #f0f0f0;">
                                    <strong><?php echo e($a['title']); ?></strong> <span class="badge <?php echo $pClass; ?>"><?php echo e($a['priority']); ?></span><br>
                                    <span class="text-muted" style="font-size:0.8rem;"><?php echo formatDate($a['created_at']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Recent Resources</h3>
                            <a href="resources.php" class="btn btn-outline btn-sm">View All</a>
                        </div>
                        <?php if (empty($recentResources)): ?>
                            <div class="empty-state"><p>No resources uploaded yet.</p></div>
                        <?php else: ?>
                            <?php foreach ($recentResources as $r):
                                $icon = $r['type'] === 'video' ? '&#9654;' : '&#128196;';
                            ?>
                                <div style="padding:0.6rem 0; border-bottom:1px solid #f0f0f0;">
                                    <?php echo $icon; ?> <strong><?php echo e($r['title']); ?></strong> <span class="badge badge-info"><?php echo e($r['type']); ?></span><br>
                                    <span class="text-muted" style="font-size:0.8rem;"><?php echo e($r['category'] ?? 'General'); ?> - <?php echo formatDate($r['created_at']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3>Quick Actions</h3></div>
                    <div class="d-flex gap-2" style="flex-wrap:wrap;">
                        <a href="marketplace.php" class="btn btn-primary">Add Product to Marketplace</a>
                        <a href="sales.php" class="btn btn-success">View Sales Records</a>
                        <a href="announcements.php" class="btn btn-secondary">Post Announcement</a>
                        <a href="resources.php" class="btn btn-outline">Upload Resource</a>
                        <a href="farmers.php" class="btn btn-outline">View Farmers</a>
                    </div>
                </div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>
    <script src="../assets/js/auth.js"></script>
</body>
</html>
