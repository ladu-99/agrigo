<?php
/**
 * AgriConnect - Farmer Notifications
 */
require_once __DIR__ . '/../../backend/check_session.php';
$user = requireLogin(['farmer', 'cooperative_leader']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Notifications</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Farmer Portal</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="resources.php"><span class="nav-icon">&#9654;</span> Resources</a>
                <a href="notifications.php" class="active"><span class="nav-icon">&#128276;</span> Notifications</a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($user['name']); ?></div>
                <div class="user-role">Farmer</div>
            </div>
        </aside>
        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">Notifications</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($user['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>
            <div class="content-area">
                <div class="page-header">
                    <h1>My Notifications</h1>
                    <button class="btn btn-outline" onclick="markAllRead()">Mark All Read</button>
                </div>
                <div id="notif-container"><div class="spinner"></div></div>
            </div>
            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>
    <script src="../assets/js/auth.js"></script>
    <script>
        async function loadNotifications() {
            const data = await API.get('../../backend/api/farmer/notifications.php');
            const container = document.getElementById('notif-container');
            if (!data || data.error || !data.notifications || data.notifications.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No notifications.</p></div>';
                return;
            }
            let html = '';
            data.notifications.forEach(function(n) {
                const readStyle = n.is_read == 1 ? 'opacity:0.7;' : 'background:#f8faf8; border-left:3px solid var(--primary);';
                html += '<div class="card" style="' + readStyle + ' padding:1rem;">';
                html += '<div class="d-flex justify-between align-center">';
                html += '<h4>' + esc(n.title) + '</h4>';
                if (n.is_read == 0) html += '<button class="btn btn-sm btn-outline" onclick="markRead(' + n.id + ')">Mark Read</button>';
                html += '</div>';
                html += '<p>' + esc(n.message) + '</p>';
                html += '<p class="text-muted" style="font-size:0.8rem;">' + UI.formatDate(n.created_at) + '</p>';
                html += '</div>';
            });
            container.innerHTML = html;
        }

        async function markRead(id) {
            await API.put('../../backend/api/farmer/notifications.php', { id: id });
            loadNotifications();
        }

        async function markAllRead() {
            await API.put('../../backend/api/farmer/notifications.php', { mark_all_read: true });
            loadNotifications();
        }

        function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        loadNotifications();
    </script>
</body>
</html>
