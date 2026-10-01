<?php
/**
 * AgriConnect - Extension Officer Reports
 * Officers forward field / market / activity reports to the admin,
 * optionally attaching a file (PDF / Word / image).
 */
require_once __DIR__ . '/../../backend/check_session.php';
$officer = requireLogin(['extension_officer']);
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
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Extension Officer Portal</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="resources.php"><span class="nav-icon">&#9654;</span> Resources</a>
                <a href="announcements.php"><span class="nav-icon">&#9993;</span> Announcements</a>
                <a href="farmers.php"><span class="nav-icon">&#9787;</span> Farmers</a>
                <a href="cooperatives.php"><span class="nav-icon">&#9881;</span> Cooperatives</a>
                <a href="marketplace.php"><span class="nav-icon">&#9733;</span> Marketplace</a>
                <a href="sales.php"><span class="nav-icon">&#9830;</span> Sales Records</a>
                <a href="inquiries.php"><span class="nav-icon">&#9990;</span> Inquiries</a>
                <a href="reports.php" class="active"><span class="nav-icon">&#9998;</span> Reports</a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($officer['name']); ?></div>
                <div class="user-role">Extension Officer</div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">Reports</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($officer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Reports to Admin</h1>
                    <button class="btn btn-primary" onclick="openReportModal()">+ Forward Report</button>
                </div>
                <div id="reports-container"><div class="spinner"></div></div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <!-- Report Modal -->
    <div class="modal-overlay" id="report-modal">
        <div class="modal" style="max-width:600px;">
            <div class="modal-header">
                <h3>Forward a Report</h3>
                <button class="modal-close" onclick="UI.toggleModal('report-modal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <form id="report-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="report-title">Report Title *</label>
                        <input type="text" id="report-title" class="form-control" placeholder="e.g., Juba Maize Field Visit - September" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="report-type">Report Type</label>
                            <select id="report-type" class="form-control">
                                <option value="field">Field Report</option>
                                <option value="market">Market Report</option>
                                <option value="activity">Activity Report</option>
                                <option value="sales">Sales Report</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reporting Period</label>
                            <div class="d-flex gap-1">
                                <input type="date" id="report-start" class="form-control">
                                <input type="date" id="report-end" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="report-desc">Description / Findings *</label>
                        <textarea id="report-desc" class="form-control" rows="5" placeholder="Summarize the report..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="report-file">Attachment (optional)</label>
                        <input type="file" id="report-file" class="form-control" accept=".pdf,.doc,.docx,image/jpeg,image/png,image/webp">
                        <small class="text-muted">PDF, Word, or image. Max 10MB.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="UI.toggleModal('report-modal', false)">Cancel</button>
                <button class="btn btn-primary" onclick="submitReport()">Send to Admin</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        function openReportModal() {
            document.getElementById('report-form').reset();
            UI.toggleModal('report-modal', true);
        }

        async function loadReports() {
            const data = await API.get('../../backend/api/extension/reports.php');
            const container = document.getElementById('reports-container');
            if (!data || !data.reports || data.reports.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">&#9998;</div><p>No reports yet. Click "Forward Report" to send one to the admin.</p></div>';
                return;
            }
            let html = '';
            data.reports.forEach(function(r) {
                const statusClass = r.status === 'new' ? 'badge-warning' : (r.status === 'reviewed' ? 'badge-success' : 'badge');
                const period = (r.period_start || r.period_end) ? ('<span class="text-muted">Period: ' + esc(r.period_start || '') + ' &rarr; ' + esc(r.period_end || '') + '</span>') : '';
                let attach = '';
                if (r.file_path) {
                    attach = '<a class="btn btn-sm btn-outline" target="_blank" href="../../backend/uploads/' + esc(r.file_path) + '">&#128206; Attachment</a>';
                }
                html += '<div class="card">';
                html += '<div class="d-flex justify-between align-center">';
                html += '<div><h3>' + esc(r.title) + '</h3><p class="text-muted" style="font-size:0.85rem;">' + esc(cap(r.report_type)) + ' &bull; ' + (r.created_at ? UI.formatDate(r.created_at) : '') + ' ' + (period ? '&bull; ' + period : '') + '</p></div>';
                html += '<span class="badge ' + statusClass + '">' + esc(r.status) + '</span>';
                html += '</div>';
                html += '<p style="margin-top:0.5rem; white-space:pre-wrap;">' + esc(r.description) + '</p>';
                html += '<div class="d-flex gap-1" style="margin-top:0.75rem;">' + attach + ' <button class="btn btn-sm btn-danger" onclick="removeReport(' + r.id + ')">Delete</button></div>';
                html += '</div>';
            });
            container.innerHTML = html;
        }

        async function submitReport() {
            const title = document.getElementById('report-title').value.trim();
            const desc = document.getElementById('report-desc').value.trim();
            if (!title || !desc) { UI.showAlert('Title and description are required.', 'warning'); return; }

            const fd = new FormData();
            fd.append('title', title);
            fd.append('report_type', document.getElementById('report-type').value);
            fd.append('period_start', document.getElementById('report-start').value);
            fd.append('period_end', document.getElementById('report-end').value);
            fd.append('description', desc);
            const fileInput = document.getElementById('report-file');
            if (fileInput.files && fileInput.files[0]) fd.append('report_file', fileInput.files[0]);

            try {
                const response = await fetch('../../backend/api/extension/reports.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await response.json();
                if (data && data.success) { UI.showAlert('Report sent to admin.', 'success'); UI.toggleModal('report-modal', false); loadReports(); }
                else { UI.showAlert((data && data.error) || 'Failed to send report.', 'danger'); }
            } catch (e) { UI.showAlert('Network error.', 'danger'); }
        }

        async function removeReport(id) {
            if (!confirm('Delete this report?')) return;
            const data = await API.request('../../backend/api/extension/reports.php', { method: 'DELETE', body: JSON.stringify({ id: id }) });
            if (data && data.success) { loadReports(); }
        }

        function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
        function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

        loadReports();
    </script>
</body>
</html>
