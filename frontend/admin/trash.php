<?php
/**
 * AgriConnect - Admin Trash / Recycle Bin
 */
require_once __DIR__ . '/../../backend/check_session.php';
$admin = requireLogin(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Trash</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .trash-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        .trash-table th, .trash-table td { padding: 0.6rem 0.8rem; text-align: left; border-bottom: 1px solid #eee; font-size: 0.9rem; vertical-align: top; }
        .trash-table th { background: #f5f5f5; color: var(--text-medium); }
        .trash-preview { color: var(--text-medium); font-size: 0.8rem; max-width: 360px; }
    </style>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Admin Panel</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="extension-officers.php"><span class="nav-icon">&#9733;</span> Extension Officers</a>
                <a href="users.php"><span class="nav-icon">&#9787;</span> Users</a>
                <a href="locations.php"><span class="nav-icon">&#9873;</span> Locations</a>
                <a href="opportunities.php"><span class="nav-icon">&#9998;</span> Opportunities</a>
                <a href="success-stories.php"><span class="nav-icon">&#10004;</span> Success Stories</a>
                <a href="messages.php"><span class="nav-icon">&#9993;</span> Messages</a>
                <a href="reports.php"><span class="nav-icon">&#9998;</span> Reports</a>
                <a href="trash.php" class="active"><span class="nav-icon">&#128465;</span> Trash</a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($admin['name']); ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">Trash Bin</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($admin['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Deleted Items</h1>
                    <button class="btn btn-danger" onclick="emptyTrash()">Empty Trash</button>
                </div>
                <p class="text-muted" style="font-size:0.85rem; margin-top:-0.5rem;">Deleted records from across the system are kept here. Restore to bring an item back, or purge to remove it permanently.</p>
                <div id="trash-container"><div class="spinner"></div></div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        async function loadTrash() {
            const data = await API.get('../../backend/api/admin/trash.php');
            const container = document.getElementById('trash-container');
            if (!data || !data.items || data.items.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">&#128465;</div><p>Trash is empty.</p></div>';
                return;
            }
            let html = '<table class="trash-table"><thead><tr><th>Item</th><th>Type</th><th>Preview</th><th>Deleted by</th><th>When</th><th>Actions</th></tr></thead><tbody>';
            data.items.forEach(function(t) {
                html += '<tr>';
                html += '<td><strong>' + esc(t.label || t.entity_type) + '</strong><div class="text-muted" style="font-size:0.75rem;">#' + esc(t.entity_id) + '</div></td>';
                html += '<td>' + esc(t.entity_type) + '</td>';
                html += '<td class="trash-preview">' + esc(previewText(t.preview)) + '</td>';
                html += '<td>' + esc(t.deleted_by_name || 'System') + '</td>';
                html += '<td style="white-space:nowrap;">' + (t.deleted_at ? UI.formatDate(t.deleted_at) : '-') + '</td>';
                html += '<td><button class="btn btn-sm btn-primary" onclick="restoreItem(' + t.id + ')">Restore</button> <button class="btn btn-sm btn-danger" onclick="purgeItem(' + t.id + ')">Purge</button></td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function previewText(preview) {
            if (!preview) return '';
            const keys = ['name', 'product_name', 'title', 'email', 'full_name', 'label', 'content', 'message', 'description'];
            for (const k of keys) {
                if (preview[k]) return k + ': ' + String(preview[k]).substring(0, 80);
            }
            const first = Object.keys(preview)[0];
            return first ? (first + ': ' + String(preview[first]).substring(0, 80)) : '';
        }

        async function restoreItem(id) {
            const data = await API.post('../../backend/api/admin/trash.php', { action: 'restore', id: id });
            if (data && data.success) { UI.showAlert('Item restored.', 'success'); loadTrash(); }
            else { UI.showAlert((data && data.error) || 'Restore failed.', 'danger'); }
        }

        async function purgeItem(id) {
            if (!confirm('Permanently delete this item? This cannot be undone.')) return;
            const data = await API.post('../../backend/api/admin/trash.php', { action: 'purge', id: id });
            if (data && data.success) { loadTrash(); }
        }

        async function emptyTrash() {
            if (!confirm('Permanently delete ALL items in the Trash? This cannot be undone.')) return;
            const data = await API.post('../../backend/api/admin/trash.php', { action: 'empty' });
            if (data && data.success) { UI.showAlert('Trash emptied.', 'success'); loadTrash(); }
        }

        function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        loadTrash();
    </script>
</body>
</html>
