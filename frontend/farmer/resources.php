<?php
/**
 * AgriConnect - Farmer Resources (Server-Rendered with Watch/Download)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$farmer = requireLogin(['farmer']);
$db = getDB();

// Get farmer's location
$stmt = $db->prepare("SELECT f.location_id, l.name as location_name FROM farmers f LEFT JOIN locations l ON l.id = f.location_id WHERE f.user_id = ?");
$stmt->execute([$farmer['id']]);
$farmerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
$locationId = $farmerInfo['location_id'] ?? $farmer['location_id'];

// Filters
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['video', 'document']) ? $_GET['type'] : null;
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : null;
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : null;

// Build query
$sql = "
    SELECT r.*, u.name as uploader_name
    FROM resources r
    LEFT JOIN extension_officers eo ON eo.id = r.uploaded_by
    LEFT JOIN users u ON u.id = eo.user_id
    WHERE r.status = 'active'
    AND (r.target_location_id = ? OR r.target_location_id IS NULL)
";
$params = [$locationId];

if ($typeFilter) {
    $sql .= " AND r.type = ?";
    $params[] = $typeFilter;
}
if ($categoryFilter) {
    $sql .= " AND r.category = ?";
    $params[] = $categoryFilter;
}
if ($searchFilter) {
    $sql .= " AND (r.title LIKE ? OR r.description LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
}
$sql .= " ORDER BY r.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter dropdown
$stmt = $db->query("SELECT DISTINCT category FROM resources WHERE status = 'active' AND category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Counts
$stmt = $db->prepare("SELECT COUNT(*) as c FROM resources WHERE status = 'active' AND (target_location_id = ? OR target_location_id IS NULL)");
$stmt->execute([$locationId]);
$totalResources = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COUNT(*) as c FROM resources WHERE status = 'active' AND type = 'video' AND (target_location_id = ? OR target_location_id IS NULL)");
$stmt->execute([$locationId]);
$totalVideos = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COUNT(*) as c FROM resources WHERE status = 'active' AND type = 'document' AND (target_location_id = ? OR target_location_id IS NULL)");
$stmt->execute([$locationId]);
$totalDocs = $stmt->fetch()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Learning Resources</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .resource-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 1.2rem;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s;
        }
        .resource-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .resource-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 0.8rem;
        }
        .resource-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .resource-icon.video { background: #e3f2fd; color: #1565c0; }
        .resource-icon.document { background: #fff3e0; color: #e65100; }
        .resource-title { font-size: 1.05rem; font-weight: 600; color: #333; margin-bottom: 0.2rem; }
        .resource-meta { font-size: 0.8rem; color: #888; }
        .resource-description { color: #555; font-size: 0.9rem; margin-bottom: 0.8rem; line-height: 1.5; }
        .resource-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-watch {
            background: #1565c0; color: white; border: none; padding: 0.5rem 1.2rem;
            border-radius: 6px; cursor: pointer; font-size: 0.9rem; font-weight: 500;
            display: inline-flex; align-items: center; gap: 0.4rem;
        }
        .btn-watch:hover { background: #0d47a1; }
        .btn-download {
            background: #2e7d32; color: white; border: none; padding: 0.5rem 1.2rem;
            border-radius: 6px; cursor: pointer; font-size: 0.9rem; font-weight: 500;
            display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none;
        }
        .btn-download:hover { background: #1b5e20; }
        .filter-bar {
            display: flex; gap: 0.8rem; flex-wrap: wrap; margin-bottom: 1.5rem;
            padding: 1rem; background: #f8f9fa; border-radius: 10px; align-items: center;
        }
        .filter-bar label { font-size: 0.85rem; font-weight: 500; color: #555; }
        .filter-bar select, .filter-bar input {
            padding: 0.4rem 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.85rem;
        }
        .filter-bar .filter-count {
            margin-left: auto; font-size: 0.85rem; color: #888;
        }
        .video-modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); z-index: 10000; align-items: center; justify-content: center;
        }
        .video-modal-overlay.active { display: flex; }
        .video-modal {
            background: #000; border-radius: 12px; overflow: hidden; width: 90%; max-width: 800px;
            position: relative;
        }
        .video-modal video { width: 100%; display: block; max-height: 70vh; }
        .video-modal-close {
            position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.9);
            border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer;
            font-size: 1.2rem; display: flex; align-items: center; justify-content: center; z-index: 10;
        }
        .video-modal-info { padding: 1rem; background: #111; color: white; }
        .video-modal-info h3 { margin: 0 0 0.3rem 0; font-size: 1rem; }
        .video-modal-info p { margin: 0; font-size: 0.85rem; color: #aaa; }
    </style>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Farmer Portal</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="resources.php" class="active"><span class="nav-icon">&#9654;</span> Resources</a>
                <a href="notifications.php"><span class="nav-icon">&#128276;</span> Notifications</a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($farmer['name']); ?></div>
                <div class="user-role">Farmer</div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">Learning Resources</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($farmer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Filter Bar -->
                <form class="filter-bar" method="GET" action="resources.php">
                    <div>
                        <label>Type:</label>
                        <select name="type" onchange="this.form.submit()">
                            <option value="">All (<?php echo $totalResources; ?>)</option>
                            <option value="video" <?php echo $typeFilter === 'video' ? 'selected' : ''; ?>>Videos (<?php echo $totalVideos; ?>)</option>
                            <option value="document" <?php echo $typeFilter === 'document' ? 'selected' : ''; ?>>Documents (<?php echo $totalDocs; ?>)</option>
                        </select>
                    </div>
                    <?php if (!empty($categories)): ?>
                    <div>
                        <label>Category:</label>
                        <select name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo e($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>><?php echo e($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label>Search:</label>
                        <input type="text" name="search" placeholder="Search resources..." value="<?php echo e($searchFilter ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <?php if ($typeFilter || $categoryFilter || $searchFilter): ?>
                        <a href="resources.php" class="btn btn-outline btn-sm">Clear</a>
                    <?php endif; ?>
                    <span class="filter-count"><?php echo count($resources); ?> resource(s) found</span>
                </form>

                <!-- Resource List -->
                <?php if (empty($resources)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">&#9654;</div>
                        <p>No resources available yet. Your extension officer will upload learning materials for you.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($resources as $r): ?>
                        <div class="resource-card">
                            <div class="resource-header">
                                <div class="resource-icon <?php echo $r['type']; ?>">
                                    <?php echo $r['type'] === 'video' ? '&#9654;' : '&#128196;'; ?>
                                </div>
                                <div>
                                    <div class="resource-title"><?php echo e($r['title']); ?></div>
                                    <div class="resource-meta">
                                        <span class="badge badge-info"><?php echo e($r['type']); ?></span>
                                        <?php if ($r['category']): ?>
                                            <span class="badge badge-success"><?php echo e($r['category']); ?></span>
                                        <?php endif; ?>
                                        <span>By <?php echo e($r['uploader_name'] ?? 'Extension Officer'); ?></span>
                                        <span>&bull; <?php echo formatDate($r['created_at']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php if ($r['description']): ?>
                                <div class="resource-description"><?php echo e($r['description']); ?></div>
                            <?php endif; ?>
                            <div class="resource-actions">
                                <?php if ($r['type'] === 'video'): ?>
                                    <button class="btn-watch" onclick="watchVideo(<?php echo $r['id']; ?>, '<?php echo e($r['file_path']); ?>', '<?php echo e($r['title']); ?>', '<?php echo e($r['description'] ?? ''); ?>')">
                                        &#9654; Watch Video
                                    </button>
                                <?php endif; ?>
                                <a class="btn-download" href="../../backend/uploads/<?php echo e($r['file_path']); ?>" download>
                                    <?php echo $r['type'] === 'video' ? '&#128229; Download Video' : '&#128229; Download Document'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <!-- Video Player Modal -->
    <div class="video-modal-overlay" id="video-modal">
        <div class="video-modal">
            <button class="video-modal-close" onclick="closeVideo()">&times;</button>
            <video id="video-player" controls>
                <source src="" type="video/mp4">
                Your browser does not support video playback.
            </video>
            <div class="video-modal-info">
                <h3 id="video-title"></h3>
                <p id="video-desc"></p>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        function watchVideo(id, filePath, title, description) {
            const modal = document.getElementById('video-modal');
            const player = document.getElementById('video-player');
            const source = player.querySelector('source');

            source.src = '../../backend/uploads/' + filePath;
            player.load();
            document.getElementById('video-title').textContent = title;
            document.getElementById('video-desc').textContent = description || '';
            modal.classList.add('active');
            player.play();
        }

        function closeVideo() {
            const modal = document.getElementById('video-modal');
            const player = document.getElementById('video-player');
            player.pause();
            player.currentTime = 0;
            modal.classList.remove('active');
        }

        // Close video on overlay click
        document.getElementById('video-modal').addEventListener('click', function(e) {
            if (e.target === this) closeVideo();
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeVideo();
        });
    </script>
</body>
</html>
