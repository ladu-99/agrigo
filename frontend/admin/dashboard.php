<?php
/**
 * AgriConnect - Admin Dashboard (Server-Rendered)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$admin = requireLogin(['admin']);
$db = getDB();

// Server-render stats
$stmt = $db->query("SELECT COUNT(*) as c FROM users WHERE role = 'extension_officer' AND status = 'active'");
$statOfficers = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM users WHERE role = 'farmer' AND status = 'active'");
$statFarmers = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM cooperatives WHERE status = 'active'");
$statCooperatives = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM market_listings WHERE status = 'active'");
$statListings = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM resources WHERE status = 'active'");
$statResources = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM opportunities WHERE status = 'active'");
$statOpportunities = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM officer_reports");
$statReports = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM officer_reports WHERE status = 'new'");
$statNewReports = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM contact_messages");
$statMessages = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM contact_messages WHERE status = 'new'");
$statNewMessages = $stmt->fetch()['c'];

// Recent users
$stmt = $db->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extension officers list
$stmt = $db->query("
    SELECT u.name, u.email, l.name as location_name, s.name as spec_name
    FROM extension_officers eo
    JOIN users u ON u.id = eo.user_id
    LEFT JOIN locations l ON l.id = eo.location_id
    LEFT JOIN specializations s ON s.id = eo.specialization_id
    ORDER BY eo.created_at DESC LIMIT 5
");
$recentOfficers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Admin Panel</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="extension-officers.php"><span class="nav-icon">&#9787;</span> Extension Officers</a>
                <a href="users.php"><span class="nav-icon">&#9787;</span> Users</a>
                <a href="locations.php"><span class="nav-icon">&#9873;</span> Locations</a>
                <a href="opportunities.php"><span class="nav-icon">&#9733;</span> Opportunities</a>
                <a href="success-stories.php"><span class="nav-icon">&#9998;</span> Success Stories</a>
                <a href="messages.php"><span class="nav-icon">&#9993;</span> Messages</a>
                <a href="reports.php"><span class="nav-icon">&#9998;</span> Reports</a>
                <a href="trash.php"><span class="nav-icon">&#128465;</span> Trash</a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($admin['name']); ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">Admin Dashboard</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($admin['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">&#9787;</div>
                        <div class="stat-info"><h4><?php echo $statOfficers; ?></h4><p>Extension Officers</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">&#9787;</div>
                        <div class="stat-info"><h4><?php echo $statFarmers; ?></h4><p>Registered Farmers</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">&#9881;</div>
                        <div class="stat-info"><h4><?php echo $statCooperatives; ?></h4><p>Cooperatives</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red">&#9733;</div>
                        <div class="stat-info"><h4><?php echo $statListings; ?></h4><p>Market Listings</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">&#9654;</div>
                        <div class="stat-info"><h4><?php echo $statResources; ?></h4><p>Resources</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">&#9733;</div>
                        <div class="stat-info"><h4><?php echo $statOpportunities; ?></h4><p>Active Opportunities</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">&#9998;</div>
                        <div class="stat-info"><h4><?php echo $statReports; ?></h4><p>Officer Reports<?php echo $statNewReports ? ' (' . $statNewReports . ' new)' : ''; ?></p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">&#9993;</div>
                        <div class="stat-info"><h4><?php echo $statMessages; ?></h4><p>Public Messages<?php echo $statNewMessages ? ' (' . $statNewMessages . ' new)' : ''; ?></p></div>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="card">
                        <div class="card-header">
                            <h3>Recent Users</h3>
                            <a href="users.php" class="btn btn-outline btn-sm">View All</a>
                        </div>
                        <?php if (empty($recentUsers)): ?>
                            <div class="empty-state"><p>No users yet.</p></div>
                        <?php else: ?>
                            <?php foreach ($recentUsers as $u): ?>
                                <div style="padding:0.5rem 0; border-bottom:1px solid #f0f0f0;">
                                    <strong><?php echo e($u['name']); ?></strong>
                                    <span class="badge badge-info"><?php echo e(str_replace('_', ' ', $u['role'])); ?></span>
                                    <span class="badge <?php echo $u['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>"><?php echo e($u['status']); ?></span><br>
                                    <span class="text-muted" style="font-size:0.8rem;"><?php echo e($u['email']); ?> - <?php echo formatDate($u['created_at']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Extension Officers</h3>
                            <a href="extension-officers.php" class="btn btn-outline btn-sm">Manage</a>
                        </div>
                        <?php if (empty($recentOfficers)): ?>
                            <div class="empty-state"><p>No extension officers yet.</p></div>
                        <?php else: ?>
                            <?php foreach ($recentOfficers as $o): ?>
                                <div style="padding:0.5rem 0; border-bottom:1px solid #f0f0f0;">
                                    <strong><?php echo e($o['name']); ?></strong><br>
                                    <span class="text-muted" style="font-size:0.8rem;">
                                        <?php echo e($o['location_name'] ?? 'N/A'); ?> - <?php echo e($o['spec_name'] ?? 'General'); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3>Quick Actions</h3></div>
                    <div class="d-flex gap-2" style="flex-wrap:wrap;">
                        <a href="extension-officers.php" class="btn btn-primary">Manage Extension Officers</a>
                        <a href="users.php" class="btn btn-outline">Manage Users</a>
                        <a href="opportunities.php" class="btn btn-outline">Manage Opportunities</a>
                        <a href="success-stories.php" class="btn btn-outline">Review Success Stories</a>
                        <a href="messages.php" class="btn btn-outline">View Messages</a>
                        <a href="reports.php" class="btn btn-outline">View Officer Reports</a>
                        <a href="trash.php" class="btn btn-outline">Trash Bin</a>
                    </div>
                </div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>
    <script src="../assets/js/auth.js"></script>
</body>
</html>
