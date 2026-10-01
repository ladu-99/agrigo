<?php
/**
 * AgriConnect - Extension Officer Marketplace Inquiries
 * Buyer requests received on the officer's product listings.
 * The officer can call or WhatsApp the buyer directly and track status.
 */
require_once __DIR__ . '/../../backend/check_session.php';
$officer = requireLogin(['extension_officer']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Inquiries</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .inquiry-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .inquiry-stats .stat { background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem; text-align: center; }
        .inquiry-stats .stat h3 { margin: 0; font-size: 1.6rem; }
        .inquiry-stats .stat p { margin: 0.2rem 0 0; color: var(--text-medium); font-size: 0.85rem; }
        .filter-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .filter-tabs button.active { background: var(--primary); color: white; border-color: var(--primary); }
        .inquiry-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        .inquiry-table th, .inquiry-table td { padding: 0.7rem 0.8rem; text-align: left; border-bottom: 1px solid #eee; vertical-align: top; font-size: 0.9rem; }
        .inquiry-table th { background: #f5f5f5; color: var(--text-medium); font-weight: 600; }
        .pill { display: inline-block; padding: 0.15rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .pill-new { background: #ffebee; color: #c62828; }
        .pill-contacted { background: #fff3e0; color: #e65100; }
        .pill-completed { background: #e8f5e9; color: #2e7d32; }
        .pill-closed { background: #eceff1; color: #607d8b; }
        .contact-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .btn-wa { background: #25d366; color: white; border: none; }
        .btn-wa:hover { background: #1ebe5b; color: white; }
        @media (max-width: 768px) { .inquiry-stats { grid-template-columns: repeat(2, 1fr); } }
    </style>
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
                <a href="inquiries.php" class="active"><span class="nav-icon">&#9990;</span> Inquiries</a>
                                <a href="reports.php"><span class="nav-icon">&#9998;</span> Reports</a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($officer['name']); ?></div>
                <div class="user-role">Extension Officer</div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">Marketplace Inquiries</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($officer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Buyer Requests &amp; Inquiries</h1>
                    <a href="marketplace.php" class="btn btn-outline">Manage Products</a>
                </div>

                <div class="inquiry-stats" id="inquiry-stats"></div>

                <div class="filter-tabs" id="filter-tabs">
                    <button class="btn btn-outline btn-sm active" data-status="" onclick="setFilter(this)">All</button>
                    <button class="btn btn-outline btn-sm" data-status="new" onclick="setFilter(this)">New</button>
                    <button class="btn btn-outline btn-sm" data-status="contacted" onclick="setFilter(this)">Contacted</button>
                    <button class="btn btn-outline btn-sm" data-status="completed" onclick="setFilter(this)">Completed</button>
                    <button class="btn btn-outline btn-sm" data-status="closed" onclick="setFilter(this)">Closed</button>
                </div>

                <div id="inquiries-container"><div class="spinner"></div></div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        let currentStatus = '';

        function setFilter(btn) {
            currentStatus = btn.getAttribute('data-status');
            document.querySelectorAll('#filter-tabs button').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            loadInquiries();
        }

        // Normalize a raw phone into a WhatsApp-ready international number (South Sudan +211).
        function waNumber(phone) {
            let d = (phone || '').replace(/[^0-9]/g, '');
            if (d.startsWith('211')) return d;
            d = d.replace(/^0+/, '');
            return '211' + d;
        }
        function telHref(phone) { return 'tel:+' + waNumber(phone); }
        function waHref(phone, productName, buyerName) {
            const text = 'Hello' + (buyerName ? ' ' + buyerName : '') + ', this is regarding your inquiry about "' + (productName || 'the product') + '" on AgriConnect.';
            return 'https://wa.me/' + waNumber(phone) + '?text=' + encodeURIComponent(text);
        }

        function statusPill(status) {
            return '<span class="pill pill-' + esc(status) + '">' + esc(cap(status)) + '</span>';
        }
        function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

        async function loadInquiries() {
            const url = '../../backend/api/extension/inquiries.php' + (currentStatus ? '?status=' + currentStatus : '');
            const data = await API.get(url);
            const container = document.getElementById('inquiries-container');
            const statsEl = document.getElementById('inquiry-stats');

            if (data && data.stats) {
                const s = data.stats;
                statsEl.innerHTML =
                    statCard(s.total, 'Total') +
                    statCard(s.cnt_new, 'New', '#c62828') +
                    statCard(s.cnt_contacted, 'Contacted', '#e65100') +
                    statCard(s.cnt_completed, 'Completed', '#2e7d32');
            }

            if (!data || !data.inquiries || data.inquiries.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">&#9990;</div><p>No inquiries yet. Buyer requests for your products will appear here.</p></div>';
                return;
            }

            let html = '<table class="inquiry-table"><thead><tr>' +
                '<th>Product</th><th>Buyer</th><th>Contact</th><th>Qty</th><th>Message</th><th>Status</th><th>Received</th><th>Actions</th>' +
                '</tr></thead><tbody>';

            data.inquiries.forEach(function(q) {
                const qty = q.quantity_wanted ? (Number(q.quantity_wanted) + ' ' + esc(q.quantity_unit || '')) : '-';
                const email = q.buyer_email ? '<div class="text-muted" style="font-size:0.8rem;">' + esc(q.buyer_email) + '</div>' : '';
                const phoneDisplay = esc(q.buyer_phone);
                let actions = '';
                if (q.status === 'new') {
                    actions += '<button class="btn btn-sm btn-primary" onclick="setStatus(' + q.id + ',\'contacted\')">Mark Contacted</button> ';
                }
                if (q.status !== 'completed') {
                    actions += '<button class="btn btn-sm btn-success" onclick="setStatus(' + q.id + ',\'completed\')">Complete</button> ';
                }
                if (q.status !== 'closed') {
                    actions += '<button class="btn btn-sm btn-outline" onclick="setStatus(' + q.id + ',\'closed\')">Close</button> ';
                }
                actions += '<button class="btn btn-sm btn-danger" onclick="removeInquiry(' + q.id + ')">Delete</button>';

                html += '<tr>';
                html += '<td><strong>' + esc(q.product_name) + '</strong><div class="text-muted" style="font-size:0.8rem;">' + esc(q.location_name || '') + '</div></td>';
                html += '<td>' + esc(q.buyer_name) + email + '</td>';
                html += '<td><div>' + phoneDisplay + '</div><div class="contact-actions" style="margin-top:0.3rem;">' +
                        '<a class="btn btn-sm btn-outline" href="' + telHref(q.buyer_phone) + '">&#128222; Call</a>' +
                        '<a class="btn btn-sm btn-wa" target="_blank" rel="noopener" href="' + waHref(q.buyer_phone, q.product_name, q.buyer_name) + '">&#9742; WhatsApp</a>' +
                        '</div></td>';
                html += '<td>' + qty + '</td>';
                html += '<td style="max-width:240px;">' + (q.message ? esc(q.message) : '-') + '</td>';
                html += '<td>' + statusPill(q.status) + '</td>';
                html += '<td style="white-space:nowrap;">' + (q.created_at ? UI.formatDate(q.created_at) : '-') + '</td>';
                html += '<td>' + actions + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function statCard(value, label, color) {
            return '<div class="stat"><h3 style="color:' + (color || 'var(--primary)') + ';">' + (value || 0) + '</h3><p>' + esc(label) + '</p></div>';
        }

        async function setStatus(id, status) {
            const data = await API.request('../../backend/api/extension/inquiries.php', { method: 'PUT', body: JSON.stringify({ id: id, status: status }) });
            if (data && data.success) { loadInquiries(); }
            else { UI.showAlert((data && data.error) || 'Failed to update.', 'danger', document.getElementById('inquiries-container')); }
        }

        async function removeInquiry(id) {
            if (!confirm('Delete this inquiry?')) return;
            const data = await API.request('../../backend/api/extension/inquiries.php', { method: 'DELETE', body: JSON.stringify({ id: id }) });
            if (data && data.success) { loadInquiries(); }
        }

        function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

        loadInquiries();
    </script>
</body>
</html>
