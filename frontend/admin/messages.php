<?php
/**
 * AgriConnect - Admin Messages (public contact inbox)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$admin = requireLogin(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Messages</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <a href="messages.php" class="active"><span class="nav-icon">&#9993;</span> Messages</a>
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
                <h1 class="page-title">Messages</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($admin['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Public Messages</h1>
                    <select id="filter-status" class="form-control" style="width:auto;" onchange="loadMessages()">
                        <option value="">All Messages</option>
                        <option value="new">New</option>
                        <option value="read">Read</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                <div id="messages-container"><div class="spinner"></div></div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        async function loadMessages() {
            const status = document.getElementById('filter-status').value;
            const data = await API.get('../../backend/api/admin/messages.php' + (status ? '?status=' + status : ''));
            const container = document.getElementById('messages-container');

            if (!data || !data.messages || data.messages.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">&#9993;</div><p>No messages.</p></div>';
                return;
            }

            let html = '';
            data.messages.forEach(function(m) {
                const statusClass = m.status === 'new' ? 'badge-warning' : (m.status === 'read' ? 'badge-info' : 'badge');
                const contactBits = [];
                if (m.email) contactBits.push('<a href="mailto:' + esc(m.email) + '">' + esc(m.email) + '</a>');
                if (m.phone) contactBits.push('<a href="tel:' + esc(m.phone) + '">' + esc(m.phone) + '</a>');
                html += '<div class="card">';
                html += '<div class="d-flex justify-between align-center">';
                html += '<div><h3>' + esc(m.subject || '(no subject)') + '</h3><p class="text-muted" style="font-size:0.85rem;">From <strong>' + esc(m.name) + '</strong>' + (contactBits.length ? ' &bull; ' + contactBits.join(' &bull; ') : '') + ' &bull; ' + (m.created_at ? UI.formatDate(m.created_at) : '') + '</p></div>';
                html += '<span class="badge ' + statusClass + '">' + esc(m.status) + '</span>';
                html += '</div>';
                html += '<p style="margin-top:0.5rem; white-space:pre-wrap;">' + esc(m.message) + '</p>';
                html += '<div class="d-flex gap-1" style="margin-top:0.75rem;">';
                if (m.status !== 'read') html += '<button class="btn btn-sm btn-primary" onclick="setMessage(' + m.id + ',\'read\')">Mark Read</button>';
                if (m.status !== 'archived') html += '<button class="btn btn-sm btn-outline" onclick="setMessage(' + m.id + ',\'archived\')">Archive</button>';
                html += '<button class="btn btn-sm btn-danger" onclick="deleteMessage(' + m.id + ')">Delete</button>';
                html += '</div></div>';
            });
            container.innerHTML = html;
        }

        async function setMessage(id, status) {
            const data = await API.request('../../backend/api/admin/messages.php', { method: 'PUT', body: JSON.stringify({ id: id, status: status }) });
            if (data && data.success) loadMessages();
        }

        async function deleteMessage(id) {
            if (!confirm('Move this message to the Trash?')) return;
            const data = await API.request('../../backend/api/admin/messages.php', { method: 'DELETE', body: JSON.stringify({ id: id }) });
            if (data && data.success) { UI.showAlert('Moved to Trash.', 'success'); loadMessages(); }
        }

        function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        loadMessages();
    </script>
</body>
</html>
