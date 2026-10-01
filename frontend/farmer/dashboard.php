<?php
/**
 * AgriConnect - Farmer Dashboard (Server-Rendered)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$farmer = requireLogin(['farmer']);
$db = getDB();

// Get farmer details
$stmt = $db->prepare("
    SELECT f.*, l.name as location_name,
    (SELECT u.name FROM extension_officers eo JOIN users u ON u.id = eo.user_id WHERE eo.id = f.assigned_extension_officer_id) as officer_name
    FROM farmers f
    LEFT JOIN locations l ON l.id = f.location_id
    WHERE f.user_id = ?
");
$stmt->execute([$farmer['id']]);
$farmerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
$locationId = $farmerInfo['location_id'] ?? $farmer['location_id'];
$officerId = $farmerInfo['assigned_extension_officer_id'] ?? null;

// Announcements for this farmer
$stmt = $db->prepare("SELECT id, title, priority, created_at FROM announcements WHERE status = 'active' AND (location_id = ? OR target_audience = 'all' OR target_audience = 'farmers') ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$locationId]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Resources
$stmt = $db->prepare("SELECT id, title, type, category, created_at FROM resources WHERE status = 'active' AND (target_location_id = ? OR target_location_id IS NULL) ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$locationId]);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Market trends
$stmt = $db->prepare("SELECT commodity, price, unit, trend, recorded_date FROM market_trends WHERE location_id = ? ORDER BY recorded_date DESC LIMIT 10");
$stmt->execute([$locationId]);
$trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Unread notifications
$stmt = $db->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$farmer['id']]);
$unreadNotifs = $stmt->fetch()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Farmer Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Farmer Portal</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="resources.php"><span class="nav-icon">&#9654;</span> Resources</a>
                <a href="notifications.php"><span class="nav-icon">&#128276;</span> Notifications <?php if ($unreadNotifs > 0): ?><span class="badge badge-danger" style="margin-left:auto;"><?php echo $unreadNotifs; ?></span><?php endif; ?></a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($farmer['name']); ?></div>
                <div class="user-role">Farmer</div>
            </div>
        </aside>
        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">Dashboard</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($farmer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>
            <div class="content-area">
                <div class="card">
                    <h2>Welcome, <?php echo e($farmer['name']); ?>!</h2>
                    <p class="text-muted">
                        Location: <?php echo e($farmerInfo['location_name'] ?? 'N/A'); ?>
                        <?php if (!empty($farmerInfo['officer_name'])): ?>
                            | Extension Officer: <?php echo e($farmerInfo['officer_name']); ?>
                        <?php endif; ?>
                        <?php if (!empty($farmerInfo['farming_type'])): ?>
                            | <?php echo e($farmerInfo['farming_type']); ?>
                            <?php echo $farmerInfo['farm_size'] ? ' (' . $farmerInfo['farm_size'] . ' ' . $farmerInfo['farm_size_unit'] . ')' : ''; ?>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">&#9993;</div>
                        <div class="stat-info"><h4><?php echo count($announcements); ?></h4><p>Announcements</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">&#9654;</div>
                        <div class="stat-info"><h4><?php echo count($resources); ?></h4><p>Resources Available</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">&#9650;</div>
                        <div class="stat-info"><h4><?php echo count($trends); ?></h4><p>Market Trends</p></div>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="card">
                        <div class="card-header"><h3>Latest Announcements</h3></div>
                        <?php if (empty($announcements)): ?>
                            <div class="empty-state"><p>No announcements.</p></div>
                        <?php else: ?>
                            <?php foreach ($announcements as $a):
                                $pClass = $a['priority'] === 'urgent' ? 'badge-danger' : ($a['priority'] === 'high' ? 'badge-warning' : 'badge-info');
                            ?>
                                <div style="padding:0.5rem 0; border-bottom:1px solid #f0f0f0;">
                                    <strong><?php echo e($a['title']); ?></strong> <span class="badge <?php echo $pClass; ?>"><?php echo e($a['priority']); ?></span><br>
                                    <span class="text-muted" style="font-size:0.8rem;"><?php echo formatDate($a['created_at']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>Market Trends</h3></div>
                        <?php if (empty($trends)): ?>
                            <div class="empty-state"><p>No market trends available.</p></div>
                        <?php else: ?>
                            <div class="table-container"><table><thead><tr><th>Commodity</th><th>Price (SSP)</th><th>Trend</th></tr></thead><tbody>
                            <?php foreach ($trends as $t):
                                $trendClass = $t['trend'] === 'up' ? 'text-success' : ($t['trend'] === 'down' ? 'text-danger' : 'text-muted');
                                $trendIcon = $t['trend'] === 'up' ? '&#9650;' : ($t['trend'] === 'down' ? '&#9660;' : '&#9654;');
                            ?>
                                <tr><td><?php echo e($t['commodity']); ?></td><td><?php echo formatSSP($t['price']); ?>/<?php echo e($t['unit']); ?></td><td class="<?php echo $trendClass; ?>"><?php echo $trendIcon; ?> <?php echo e($t['trend']); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody></table></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Learning Resources</h3>
                        <a href="resources.php" class="btn btn-outline btn-sm">View All & Download</a>
                    </div>
                    <?php if (empty($resources)): ?>
                        <div class="empty-state"><p>No resources available.</p></div>
                    <?php else: ?>
                        <div class="d-flex gap-2" style="flex-wrap:wrap;">
                            <?php foreach ($resources as $r):
                                $icon = $r['type'] === 'video' ? '&#9654;' : '&#128196;';
                            ?>
                                <div class="card" style="flex:1; min-width:200px; margin-bottom:0;">
                                    <p><?php echo $icon; ?> <strong><?php echo e($r['title']); ?></strong></p>
                                    <span class="badge badge-info"><?php echo e($r['type']); ?></span>
                                    <span class="text-muted" style="font-size:0.8rem;"><?php echo e($r['category'] ?? ''); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>
    <script src="../assets/js/auth.js"></script>
</body>
</html>
