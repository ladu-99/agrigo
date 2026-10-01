<?php
/**
 * AgriConnect - Announcements (Extension Officer)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$officer = requireLogin(['extension_officer']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Announcements</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Extension Officer Portal</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="resources.php"><span class="nav-icon">&#9654;</span> Resources</a>
                <a href="announcements.php" class="active"><span class="nav-icon">&#9993;</span> Announcements</a>
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
                <h1 class="page-title">Announcements</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($officer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>
            <div class="content-area">
                <div class="page-header">
                    <h1>Announcements</h1>
                    <button class="btn btn-primary" onclick="openAddModal()">+ Post Announcement</button>
                </div>
                <div id="announcements-container"><div class="spinner"></div></div>
            </div>
            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <div class="modal-overlay" id="ann-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Post Announcement</h3>
                <button class="modal-close" onclick="UI.toggleModal('ann-modal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <form id="ann-form">
                    <div class="form-group">
                        <label for="ann-title">Title *</label>
                        <input type="text" id="ann-title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="ann-content">Content *</label>
                        <textarea id="ann-content" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ann-priority">Priority</label>
                            <select id="ann-priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ann-audience">Target Audience</label>
                            <select id="ann-audience" class="form-control">
                                <option value="all">All</option>
                                <option value="farmers">Farmers</option>
                                <option value="cooperatives">Cooperatives</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="UI.toggleModal('ann-modal', false)">Cancel</button>
                <button class="btn btn-primary" onclick="saveAnnouncement()">Post & Notify Farmers</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        async function loadAnnouncements() {
            const data = await API.get('../../backend/api/extension/announcements.php');
            const container = document.getElementById('announcements-container');

            if (!data || data.error || !data.announcements || data.announcements.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No announcements posted yet.</p></div>';
                return;
            }

            let html = '';
            data.announcements.forEach(function(a) {
                const pClass = a.priority === 'urgent' ? 'badge-danger' : (a.priority === 'high' ? 'badge-warning' : (a.priority === 'medium' ? 'badge-info' : 'badge-success'));
                html += '<div class="card">';
                html += '<div class="d-flex justify-between align-center">';
                html += '<h3>' + esc(a.title) + '</h3>';
                html += '<div class="d-flex gap-1"><span class="badge ' + pClass + '">' + a.priority + '</span>';
                html += '<button class="btn btn-danger btn-sm" onclick="deleteAnn(' + a.id + ')">Delete</button></div>';
                html += '</div>';
                html += '<p>' + esc(a.content) + '</p>';
                html += '<p class="text-muted" style="font-size:0.8rem;">' + esc(a.location_name || '') + ' | ' + UI.formatDate(a.created_at) + '</p>';
                html += '</div>';
            });
            container.innerHTML = html;
        }

        function openAddModal() {
            document.getElementById('ann-form').reset();
            UI.toggleModal('ann-modal', true);
        }

        async function saveAnnouncement() {
            const title = document.getElementById('ann-title').value.trim();
            const content = document.getElementById('ann-content').value.trim();
            if (!title || !content) { UI.showAlert('Title and content are required.', 'warning'); return; }

            const data = await API.post('../../backend/api/extension/announcements.php', {
                title: title, content: content,
                priority: document.getElementById('ann-priority').value,
                target_audience: document.getElementById('ann-audience').value
            });

            if (data && data.success) {
                UI.showAlert('Announcement posted and farmers notified!', 'success');
                UI.toggleModal('ann-modal', false);
                loadAnnouncements();
            } else {
                UI.showAlert(data.error || 'Failed.', 'danger');
            }
        }

        async function deleteAnn(id) {
            if (!confirm('Delete this announcement?')) return;
            const data = await API.request('../../backend/api/extension/announcements.php', { method: 'DELETE', body: JSON.stringify({ id: id }) });
            if (data && data.success) { UI.showAlert('Deleted.', 'success'); loadAnnouncements(); }
        }

        function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        loadAnnouncements();
    </script>
</body>
</html>
