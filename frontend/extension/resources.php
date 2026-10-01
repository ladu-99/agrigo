<?php
/**
 * AgriConnect - Extension Officer Resources (PHP + Real File Upload)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$officer = requireLogin(['extension_officer']);
$eoId = $officer['extension_officer_id'];
$db = getDB();

// Handle file upload via POST
$uploadMessage = '';
$uploadError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_resource') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? '';
    $category = trim($_POST['category'] ?? '');

    if (empty($title) || empty($type)) {
        $uploadError = 'Title and type are required.';
    } elseif (!isset($_FILES['resource_file']) || $_FILES['resource_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'Please select a file to upload.';
    } else {
        $file = $_FILES['resource_file'];
        $maxSize = 50 * 1024 * 1024; // 50MB

        if ($file['size'] > $maxSize) {
            $uploadError = 'File must be less than 50MB.';
        } else {
            // Determine upload subdirectory
            $subDir = $type === 'video' ? 'resources' : 'documents';
            $uploadDir = __DIR__ . '/../../backend/uploads/' . $subDir . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Validate file type
            $allowedVideo = ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-msvideo'];
            $allowedDoc = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed = array_merge($allowedVideo, $allowedDoc);
            if (!in_array($mimeType, $allowed)) {
                $uploadError = 'Invalid file type. Allowed: PDF, DOC, DOCX, MP4, AVI, MOV.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = $subDir . '_' . time() . '_' . uniqid() . '.' . $ext;
                $destination = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $filePath = $subDir . '/' . $filename;

                    $stmt = $db->prepare("
                        INSERT INTO resources (title, description, type, file_path, uploaded_by, category, target_location_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title,
                        $description ?: null,
                        $type,
                        $filePath,
                        $eoId,
                        $category ?: null,
                        $officer['location_id'] ?? null
                    ]);

                    // Notify farmers assigned to this officer
                    $stmt = $db->prepare("
                        SELECT u.id FROM users u
                        JOIN farmers f ON f.user_id = u.id
                        WHERE f.assigned_extension_officer_id = ?
                    ");
                    $stmt->execute([$eoId]);
                    $farmers = $stmt->fetchAll();

                    foreach ($farmers as $farmer) {
                        $stmt = $db->prepare("
                            INSERT INTO notifications (user_id, title, message, type)
                            VALUES (?, 'New Learning Resource', ?, 'resource')
                        ");
                        $stmt->execute([$farmer['id'], 'New resource uploaded: ' . $title]);
                    }

                    $uploadMessage = 'Resource uploaded successfully! Farmers have been notified.';
                } else {
                    $uploadError = 'Failed to save the file. Please try again.';
                }
            }
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_resource') {
    $resId = (int)($_POST['resource_id'] ?? 0);
    if ($resId > 0) {
        $stmt = $db->prepare("DELETE FROM resources WHERE id = ? AND uploaded_by = ?");
        $stmt->execute([$resId, $eoId]);
        $uploadMessage = 'Resource deleted.';
    }
}

// Load resources
$stmt = $db->prepare("
    SELECT r.*, l.name as target_location_name
    FROM resources r
    LEFT JOIN locations l ON l.id = r.target_location_id
    WHERE r.uploaded_by = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$eoId]);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Resources</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Extension Officer Portal</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="resources.php" class="active"><span class="nav-icon">&#9654;</span> Resources</a>
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
                <h1 class="page-title">Learning Resources</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($officer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Resources (Videos & Documents)</h1>
                    <button class="btn btn-primary" onclick="document.getElementById('upload-modal').classList.add('active')">+ Upload Resource</button>
                </div>

                <?php if ($uploadMessage): ?>
                    <div class="alert alert-success"><span><?php echo e($uploadMessage); ?></span></div>
                <?php endif; ?>
                <?php if ($uploadError): ?>
                    <div class="alert alert-danger"><span><?php echo e($uploadError); ?></span></div>
                <?php endif; ?>

                <?php if (empty($resources)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">&#9654;</div>
                        <p>No resources uploaded yet. Click "Upload Resource" to add learning materials for farmers.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr><th>Title</th><th>Type</th><th>Category</th><th>File</th><th>Date</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($resources as $r):
                                $icon = $r['type'] === 'video' ? '&#9654;' : '&#128196;';
                            ?>
                                <tr>
                                    <td><?php echo $icon; ?> <strong><?php echo e($r['title']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo e($r['type']); ?></span></td>
                                    <td><?php echo e($r['category'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($r['file_path']): ?>
                                            <a href="../../backend/uploads/<?php echo e($r['file_path']); ?>" target="_blank" class="btn btn-outline btn-sm">View File</a>
                                        <?php else: ?>
                                            <span class="text-muted">No file</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($r['created_at']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this resource?');">
                                            <input type="hidden" name="action" value="delete_resource">
                                            <input type="hidden" name="resource_id" value="<?php echo $r['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal-overlay" id="upload-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Upload Learning Resource</h3>
                <button class="modal-close" onclick="document.getElementById('upload-modal').classList.remove('active')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_resource">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="res-title">Title *</label>
                        <input type="text" id="res-title" name="title" class="form-control" placeholder="e.g., Best Practices for Maize Cultivation" required>
                    </div>
                    <div class="form-group">
                        <label for="res-description">Description</label>
                        <textarea id="res-description" name="description" class="form-control" rows="3" placeholder="Describe what farmers will learn from this resource..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="res-type">Type *</label>
                            <select id="res-type" name="type" class="form-control" required>
                                <option value="document">Document (PDF, DOC)</option>
                                <option value="video">Video (MP4)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="res-category">Category</label>
                            <input type="text" id="res-category" name="category" class="form-control" placeholder="e.g., Crop Production, Livestock">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>File *</label>
                        <div class="file-upload" onclick="document.getElementById('res-file').click()">
                            <div class="upload-icon">&#128193;</div>
                            <div class="upload-text" id="upload-text">Click to select file</div>
                            <div class="upload-hint">Documents: PDF, DOC (max 10MB). Videos: MP4 (max 50MB).</div>
                            <input type="file" id="res-file" name="resource_file" accept=".pdf,.doc,.docx,.mp4,.avi,.mov" required onchange="document.getElementById('upload-text').textContent = this.files[0] ? this.files[0].name : 'Click to select file'" style="display:none;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('upload-modal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Resource</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
</body>
</html>
