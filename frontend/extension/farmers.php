<?php
/**
 * AgriConnect - Farmers (Extension Officer)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$officer = requireLogin(['extension_officer']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Farmers</title>
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
                <a href="farmers.php" class="active"><span class="nav-icon">&#9787;</span> Farmers</a>
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
                <h1 class="page-title">My Farmers</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($officer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>
            <div class="content-area">
                <div class="page-header"><h1>Assigned Farmers</h1><p style="color:#666; margin:0.2rem 0 0;">Review and approve pending farmer registrations for your area. Approved farmers can then sign in and receive resources and notifications.</p></div>
                <div id="farmers-container"><div class="spinner"></div></div>
            </div>
            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>
    <div class="modal-overlay" id="profile-modal">
        <div class="modal" style="max-width:560px;">
            <div class="modal-header">
                <h3>Farmer Profile</h3>
                <button class="modal-close" onclick="UI.toggleModal('profile-modal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-center" style="gap:1rem; margin-bottom:1rem;">
                    <div id="pf-avatar"></div>
                    <div>
                        <h2 style="margin:0;" id="pf-name">-</h2>
                        <div id="pf-status" style="margin-top:0.3rem;"></div>
                    </div>
                </div>
                <div class="table-container"><table><tbody>
                    <tr><th>Email</th><td id="pf-email">-</td></tr>
                    <tr><th>Phone</th><td id="pf-phone">-</td></tr>
                    <tr><th>Location</th><td id="pf-location">-</td></tr>
                    <tr><th>Farm Size</th><td id="pf-farm">-</td></tr>
                    <tr><th>Farming Type</th><td id="pf-type">-</td></tr>
                    <tr><th>Cooperative(s)</th><td id="pf-coop">-</td></tr>
                    <tr><th>Registered</th><td id="pf-joined">-</td></tr>
                </tbody></table></div>
                <div class="form-group" style="margin-top:1rem;">
                    <label for="pf-photo-input">Profile photo</label>
                    <input type="file" id="pf-photo-input" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small class="text-muted" style="font-size:0.78rem;">JPEG, PNG, WEBP or GIF, up to 5MB.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="UI.toggleModal('profile-modal', false)">Close</button>
                <button class="btn btn-primary" id="pf-photo-btn" onclick="savePhoto()">Save Photo</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        async function loadFarmers() {
            const data = await API.get('../../backend/api/extension/farmers.php');
            const container = document.getElementById('farmers-container');

            if (!data || data.error || !data.farmers || data.farmers.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No farmers assigned to your area yet.</p></div>';
                return;
            }

            let html = '<div class="table-container"><table><thead><tr><th style="width:64px;"></th><th>Name</th><th>Phone</th><th>Email</th><th>Farm Size</th><th>Farming Type</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
            data.farmers.forEach(function(f) {
                html += '<tr>';
                html += '<td>' + avatarCell(f, 44) + '</td>';
                html += '<td><strong>' + esc(f.name) + '</strong></td>';
                html += '<td>' + esc(f.phone || '-') + '</td>';
                html += '<td>' + esc(f.email || '-') + '</td>';
                html += '<td>' + (f.farm_size ? f.farm_size + ' ' + (f.farm_size_unit || 'acres') : '-') + '</td>';
                html += '<td>' + esc(f.farming_type || '-') + '</td>';
                html += '<td>' + esc(f.location_name || '-') + '</td>';
                html += '<td>' + statusBadge(f.status) + '</td>';
                html += '<td>';
                html += '<button class="btn btn-outline btn-sm" onclick="openProfile(' + f.user_id + ')">View</button> ';
                if (f.status === 'pending') {
                    html += '<button class="btn btn-success btn-sm" onclick="decide(' + f.user_id + ',\'approve\')">Approve</button> ';
                    html += '<button class="btn btn-danger btn-sm" onclick="decide(' + f.user_id + ',\'reject\')">Reject</button>';
                }
                html += '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

        function photoUrl(p) { return p ? '../../backend/uploads/' + p : ''; }

        function avatarCell(f, size) {
            size = size || 44;
            if (f.profile_photo) {
                return '<img src="' + photoUrl(f.profile_photo) + '" alt="" style="width:' + size + 'px;height:' + size + 'px;border-radius:50%;object-fit:cover;border:1px solid #ddd;display:block;">';
            }
            var initials = (f.name || '?').trim().split(/\s+/).map(function(w){ return w.charAt(0); }).slice(0,2).join('').toUpperCase();
            var fs = Math.round(size * 0.4);
            return '<div style="width:' + size + 'px;height:' + size + 'px;border-radius:50%;background:#2e7d32;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:' + fs + 'px;">' + esc(initials) + '</div>';
        }

        let profileUserId = null;
        async function openProfile(userId) {
            const data = await API.get('../../backend/api/extension/farmer-profile.php?user_id=' + userId);
            if (!data || data.error || !data.farmer) { UI.showAlert((data && data.error) || 'Could not load profile', 'error'); return; }
            const f = data.farmer;
            profileUserId = userId;
            document.getElementById('pf-avatar').innerHTML = avatarCell(f, 96);
            document.getElementById('pf-name').textContent = f.name || '-';
            document.getElementById('pf-status').innerHTML = statusBadge(f.status);
            document.getElementById('pf-email').textContent = f.email || '-';
            document.getElementById('pf-phone').textContent = f.phone || '-';
            document.getElementById('pf-location').textContent = f.location_name || '-';
            document.getElementById('pf-farm').textContent = f.farm_size ? (f.farm_size + ' ' + (f.farm_size_unit || 'acres')) : '-';
            document.getElementById('pf-type').textContent = f.farming_type || '-';
            document.getElementById('pf-joined').textContent = f.created_at ? UI.formatDate(f.created_at) : '-';
            const coopEl = document.getElementById('pf-coop');
            if (f.cooperatives && f.cooperatives.length) {
                coopEl.innerHTML = f.cooperatives.map(function(c){ return '<span class="badge badge-info">' + esc(c.name) + ' (' + esc(c.role) + ')</span>'; }).join(' ');
            } else { coopEl.textContent = 'None'; }
            document.getElementById('pf-photo-input').value = '';
            UI.toggleModal('profile-modal', true);
        }

        async function savePhoto() {
            const inp = document.getElementById('pf-photo-input');
            if (!inp.files || !inp.files[0]) { UI.showAlert('Choose an image first', 'error'); return; }
            const btn = document.getElementById('pf-photo-btn');
            btn.disabled = true; btn.textContent = 'Uploading...';
            try {
                const fd = new FormData();
                fd.append('action', 'photo');
                fd.append('user_id', profileUserId);
                fd.append('photo', inp.files[0]);
                const res = await API.upload('../../backend/api/extension/farmer-profile.php', fd);
                if (res && res.success) {
                    UI.showAlert('Photo updated', 'success');
                    document.getElementById('pf-avatar').innerHTML = avatarCell({ name: document.getElementById('pf-name').textContent, profile_photo: res.profile_photo }, 96);
                    loadFarmers();
                } else {
                    UI.showAlert((res && res.error) || 'Upload failed', 'error');
                }
            } catch (e) {
                UI.showAlert('Upload failed', 'error');
            } finally {
                btn.disabled = false; btn.textContent = 'Save Photo';
            }
        }

        function statusBadge(s) {
            var colors = { active: '#2e7d32', pending: '#ef6c00', inactive: '#757575', suspended: '#c62828', rejected: '#c62828' };
            var c = colors[s] || '#757575';
            return '<span class="badge" style="background:' + c + ';color:#fff;">' + esc(s) + '</span>';
        }

        async function decide(userId, action) {
            const res = await API.post('../../backend/api/extension/farmer-approval.php', { user_id: userId, action: action });
            if (res && res.success) { loadFarmers(); } else { alert((res && res.error) || 'Action failed'); }
        }

        loadFarmers();
    </script>
</body>
</html>
