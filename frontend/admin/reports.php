<?php
/**
 * AgriConnect - Admin Officer Reports inbox
 */
require_once __DIR__ . '/../../backend/check_session.php';
$admin = requireLogin(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Reports</title>
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
                <a href="messages.php"><span class="nav-icon">&#9993;</span> Messages</a>
                <a href="reports.php" class="active"><span class="nav-icon">&#9998;</span> Reports</a>
                <a href="trash.php"><span class="nav-icon">&#128465;</span> Trash</a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($admin['name']); ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">Officer Reports</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($admin['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Reports from Extension Officers</h1>
                    <div class="d-flex gap-1">
                        <select id="filter-type" class="form-control" style="width:auto;" onchange="loadReports()">
                            <option value="">All Types</option>
                            <option value="field">Field</option>
                            <option value="market">Market</option>
                            <option value="activity">Activity</option>
                            <option value="sales">Sales</option>
                            <option value="other">Other</option>
                        </select>
                        <select id="filter-status" class="form-control" style="width:auto;" onchange="loadReports()">
                            <option value="">All</option>
                            <option value="new">New</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
                <div id="reports-container"><div class="spinner"></div></div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        async function loadReports() {
            const p = new URLSearchParams();
            const type = document.getElementById('filter-type').value;
            const status = document.getElementById('filter-status').value;
            if (type) p.set('type', type);
            if (status) p.set('status', status);
            const qs = p.toString();
            const data = await API.get('../../backend/api/admin/reports.php' + (qs ? '?' + qs : ''));
            const container = document.getElementById('reports-container');

            if (!data || !data.reports || data.reports.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">&#9998;</div><p>No reports have been forwarded yet.</p></div>';
                return;
            }

            let html = '';
            data.reports.forEach(function(r) {
                const statusClass = r.status === 'new' ? 'badge-warning' : (r.status === 'reviewed' ? 'badge-success' : 'badge');
                const period = (r.period_start || r.period_end) ? (' &bull; Period: ' + esc(r.period_start || '') + ' &rarr; ' + esc(r.period_end || '')) : '';
                let attach = '';
                if (r.file_path) attach = '<a class="btn btn-sm btn-outline" target="_blank" href="../../backend/uploads/' + esc(r.file_path) + '">&#128206; Attachment</a>';
                html += '<div class="card">';
                html += '<div class="d-flex justify-between align-center">';
                html += '<div><h3>' + esc(r.title) + '</h3><p class="text-muted" style="font-size:0.85rem;">By <strong>' + esc(r.officer_name) + '</strong> (' + esc(r.location_name || 'N/A') + ') &bull; ' + esc(cap(r.report_type)) + ' &bull; ' + (r.created_at ? UI.formatDate(r.created_at) : '') + period + '</p></div>';
                html += '<span class="badge ' + statusClass + '">' + esc(r.status) + '</span>';
                html += '</div>';
                html += '<p style="margin-top:0.5rem; white-space:pre-wrap;">' + esc(r.description) + '</p>';
                html += '<div class="d-flex gap-1" style="margin-top:0.75rem;">' + attach;
                if (r.status !== 'reviewed') html += '<button class="btn btn-sm btn-primary" onclick="setReport(' + r.id + ',\'reviewed\')">Mark Reviewed</button>';
                if (r.status !== 'archived') html += '<button class="btn btn-sm btn-outline" onclick="setReport(' + r.id + ',\'archived\')">Archive</button>';
                html += '<button class="btn btn-sm btn-danger" onclick="deleteReport(' + r.id + ')">Delete</button>';
                html += '</div></div>';
            });
            container.innerHTML = html;
        }

        async function setReport(id, status) {
            const data = await API.request('../../backend/api/admin/reports.php', { method: 'PUT', body: JSON.stringify({ id: id, status: status }) });
            if (data && data.success) loadReports();
        }

        async function deleteReport(id) {
            if (!confirm('Move this report to the Trash?')) return;
            const data = await API.request('../../backend/api/admin/reports.php', { method: 'DELETE', body: JSON.stringify({ id: id }) });
            if (data && data.success) { UI.showAlert('Moved to Trash.', 'success'); loadReports(); }
        }

        function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
        function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        loadReports();
    </script>
</body>
</html>
